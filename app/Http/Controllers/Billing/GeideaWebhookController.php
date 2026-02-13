<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\FinancialTransaction;
use App\Models\Billing\PaymentAttempt;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionEvent;
use App\Services\GeideaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeideaWebhookController extends Controller
{
    /**
     * Geidea payment callback (webhook). No auth – called by Geidea.
     */
    public function callback(Request $request)
    {
        $payload = $request->all();
        Log::info('Geidea callback received', ['payload_keys' => array_keys($payload)]);

        $geidea = new GeideaService();
        if (!$geidea->verifyCallbackSignature($payload)) {
            Log::warning('Geidea callback signature invalid');
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $order = $payload['order'] ?? [];
        $orderId = $order['orderId'] ?? $payload['orderId'] ?? null;
        $merchantRef = $order['merchantReferenceId'] ?? $payload['merchantReferenceId'] ?? null;
        $status = $order['status'] ?? $payload['detailedStatus'] ?? $payload['status'] ?? '';
        $responseCode = $payload['responseCode'] ?? '';
        $detailedResponseCode = $payload['detailedResponseCode'] ?? '';

        if (!$merchantRef) {
            Log::warning('Geidea callback missing merchantReferenceId');
            return response()->json(['message' => 'Missing merchant reference'], 400);
        }

        $attempt = PaymentAttempt::where('merchant_reference', $merchantRef)->first();
        if (!$attempt) {
            Log::warning('Geidea callback unknown merchant_reference', ['merchant_reference' => $merchantRef]);
            return response()->json(['message' => 'Unknown reference'], 404);
        }

        $success = ($responseCode === '000' && $detailedResponseCode === '000' && in_array(strtolower($status), ['paid', 'completed', 'captured'], true));

        try {
            DB::transaction(function () use ($attempt, $payload, $orderId, $success, $order) {
                $attempt->geidea_order_id = $orderId ?? $attempt->geidea_order_id;
                $attempt->status = $success ? 'completed' : 'failed';
                if (!$success) {
                    $attempt->failure_reason = $payload['detailedResponseMessage'] ?? $payload['responseMessage'] ?? $order['detailedStatus'] ?? 'Payment failed';
                }
                $attempt->verified_at = $success ? now() : null;
                $attempt->metadata = array_merge($attempt->metadata ?? [], ['callback' => $payload]);
                $attempt->save();

                if ($success) {
                    $this->createOrLinkSubscription($attempt, $order);
                }
            });
        } catch (\Throwable $e) {
            Log::error('Geidea callback processing failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Processing error'], 500);
        }

        return response()->json(['message' => 'OK'], 200);
    }

    protected function createOrLinkSubscription(PaymentAttempt $attempt, array $order): void
    {
        if ($attempt->subscription_id) {
            $sub = Subscription::find($attempt->subscription_id);
            if ($sub) {
                $sub->geidea_order_id = $order['orderId'] ?? $sub->geidea_order_id;
                $sub->save();
                $this->recordSubscriptionEventAndTransaction($sub, $attempt, 'created');
            }
            return;
        }

        $plan = Plan::find($attempt->plan_id);
        if (!$plan) {
            return;
        }

        $intervalDays = match (strtolower($plan->interval)) {
            'monthly' => 30,
            'semi_annual' => 180,
            'annual' => 365,
            default => 30,
        };

        $startDate = Carbon::now();
        $endDate = $startDate->copy()->addDays($intervalDays);

        $subscription = Subscription::create([
            'user_id' => $attempt->user_id,
            'plan_id' => $attempt->plan_id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'status' => 'active',
            'price' => $attempt->amount,
            'currency' => $attempt->currency,
            'auto_renew' => !empty($attempt->geidea_subscription_id),
            'geidea_subscription_id' => $attempt->geidea_subscription_id,
            'geidea_order_id' => $order['orderId'] ?? null,
        ]);

        $attempt->subscription_id = $subscription->id;
        $attempt->save();

        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'event_type' => 'created',
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => $subscription->start_date,
            'end_date' => $subscription->end_date,
            'plan_price' => $plan->price,
            'amount_charged' => $attempt->amount,
            'amount_refunded' => 0,
            'currency' => $subscription->currency,
            'meta' => ['geidea_order_id' => $order['orderId'] ?? null, 'payment_attempt_id' => $attempt->id],
        ]);

        FinancialTransaction::create([
            'subscription_id' => $subscription->id,
            'user_id' => $attempt->user_id,
            'type' => 'subscription_payment',
            'amount' => $attempt->amount,
            'currency' => $attempt->currency,
            'status' => 'completed',
            'description' => 'Subscription payment via Geidea',
            'metadata' => ['geidea_order_id' => $order['orderId'] ?? null, 'payment_attempt_id' => $attempt->id],
            'processed_at' => now(),
        ]);
    }

    protected function recordSubscriptionEventAndTransaction(Subscription $subscription, PaymentAttempt $attempt, string $eventType): void
    {
        $plan = $subscription->plan;
        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'event_type' => $eventType,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => $subscription->start_date,
            'end_date' => $subscription->end_date,
            'plan_price' => $plan->price,
            'amount_charged' => $attempt->amount,
            'amount_refunded' => 0,
            'currency' => $subscription->currency,
            'meta' => ['geidea_order_id' => $attempt->geidea_order_id, 'payment_attempt_id' => $attempt->id],
        ]);
        FinancialTransaction::create([
            'subscription_id' => $subscription->id,
            'user_id' => $attempt->user_id,
            'type' => 'subscription_payment',
            'amount' => $attempt->amount,
            'currency' => $attempt->currency,
            'status' => 'completed',
            'description' => 'Subscription payment via Geidea',
            'metadata' => ['geidea_order_id' => $attempt->geidea_order_id],
            'processed_at' => now(),
        ]);
    }
}
