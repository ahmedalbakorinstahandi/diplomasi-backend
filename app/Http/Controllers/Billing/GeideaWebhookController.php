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
        $paymentIntent = $payload['paymentIntent'] ?? [];
        $orderId = $order['orderId'] ?? $payload['orderId'] ?? $paymentIntent['orderId'] ?? null;
        $merchantRef = $order['merchantReferenceId'] ?? $payload['merchantReferenceId']
            ?? $paymentIntent['eInvoiceDetails']['merchantReferenceId'] ?? $paymentIntent['merchantReferenceId'] ?? null;
        $status = $order['status'] ?? $payload['detailedStatus'] ?? $payload['status'] ?? $paymentIntent['status'] ?? '';
        $responseCode = $payload['responseCode'] ?? '';
        $detailedResponseCode = $payload['detailedResponseCode'] ?? '';
        $success = ($responseCode === '000' && $detailedResponseCode === '000' && in_array(strtolower($status), ['paid', 'completed', 'captured'], true));

        if ($merchantRef) {
            $orderForHandler = $order;
            if (empty($orderForHandler['orderId']) && $orderId) {
                $orderForHandler['orderId'] = $orderId;
            }
            return $this->handlePaymentAttemptCallback($merchantRef, $orderId, $orderForHandler, $payload, $success);
        }

        $subscriptionId = $this->extractSubscriptionId($payload, $order);
        if ($subscriptionId) {
            return $this->handleRecurringRenewalCallback($subscriptionId, $orderId, $order, $payload, $success);
        }

        Log::warning('Geidea callback ignored: no merchant reference and no subscription id');
        return response()->json(['message' => 'Ignored'], 202);
    }

    protected function handlePaymentAttemptCallback(
        string $merchantRef,
        ?string $orderId,
        array $order,
        array $payload,
        bool $success
    ) {
        $attempt = PaymentAttempt::where('merchant_reference', $merchantRef)->first();
        if (!$attempt) {
            Log::warning('Geidea callback unknown merchant_reference', ['merchant_reference' => $merchantRef]);
            return response()->json(['message' => 'Unknown reference'], 404);
        }

        try {
            DB::transaction(function () use ($attempt, $payload, $orderId, $success, $order) {
                $attempt->geidea_order_id = $orderId ?? $attempt->geidea_order_id;
                $attempt->metadata = array_merge($attempt->metadata ?? [], ['callback' => $payload]);

                if (!$success) {
                    if ($attempt->status !== 'completed') {
                        $attempt->status = 'failed';
                        $attempt->failure_reason = $payload['detailedResponseMessage'] ?? $payload['responseMessage'] ?? $order['detailedStatus'] ?? 'Payment failed';
                    }
                    $attempt->save();
                    return;
                }

                if ($attempt->status !== 'completed') {
                    $attempt->status = 'completed';
                    $attempt->verified_at = now();
                }
                $attempt->save();

                $this->createOrLinkSubscription($attempt, $order);
            });
        } catch (\Throwable $e) {
            Log::error('Geidea callback processing failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Processing error'], 500);
        }

        return response()->json(['message' => 'OK'], 200);
    }

    protected function handleRecurringRenewalCallback(
        string $geideaSubscriptionId,
        ?string $orderId,
        array $order,
        array $payload,
        bool $success
    ) {
        $subscription = Subscription::where('geidea_subscription_id', $geideaSubscriptionId)->first();
        if (!$subscription) {
            Log::warning('Geidea recurring callback unknown subscription', ['geidea_subscription_id' => $geideaSubscriptionId]);
            return response()->json(['message' => 'Unknown subscription'], 404);
        }

        if (!$success) {
            Log::warning('Geidea recurring callback failed', [
                'subscription_id' => $subscription->id,
                'geidea_subscription_id' => $geideaSubscriptionId,
                'order_id' => $orderId,
                'payload' => $payload,
            ]);
            return response()->json(['message' => 'Ignored failed recurring callback'], 200);
        }

        if ($orderId) {
            $alreadyProcessed = SubscriptionEvent::query()
                ->where('subscription_id', $subscription->id)
                ->where('event_type', 'renewed')
                ->where('meta->geidea_order_id', $orderId)
                ->exists();
            if ($alreadyProcessed) {
                return response()->json(['message' => 'OK'], 200);
            }
        }

        DB::transaction(function () use ($subscription, $orderId, $order) {
            $intervalDays = match (strtolower($subscription->plan?->interval ?? 'monthly')) {
                'monthly' => 30,
                'semi_annual' => 180,
                'annual' => 365,
                default => 30,
            };

            $currentEnd = Carbon::parse($subscription->end_date);
            $baseStart = $currentEnd->isFuture() ? $currentEnd : Carbon::now();
            $newEnd = $baseStart->copy()->addDays($intervalDays);
            $chargedAmount = (float) ($order['amount'] ?? $subscription->price ?? 0);

            $subscription->update([
                'status' => 'active',
                'auto_renew' => true,
                'geidea_order_id' => $orderId ?? $subscription->geidea_order_id,
                'end_date' => $newEnd->toDateString(),
            ]);

            $event = SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'event_type' => 'renewed',
                'plan_id' => $subscription->plan_id,
                'status' => 'active',
                'start_date' => $currentEnd->toDateString(),
                'end_date' => $newEnd->toDateString(),
                'plan_price' => $subscription->plan?->price ?? $subscription->price,
                'amount_charged' => $chargedAmount,
                'amount_refunded' => 0,
                'currency' => $subscription->currency,
                'meta' => [
                    'geidea_order_id' => $orderId,
                    'geidea_subscription_id' => $subscription->geidea_subscription_id,
                    'source' => 'geidea_recurring_callback',
                ],
            ]);

            FinancialTransaction::create([
                'subscription_id' => $subscription->id,
                'subscription_event_id' => $event->id,
                'user_id' => $subscription->user_id,
                'type' => 'subscription_payment',
                'amount' => $chargedAmount,
                'currency' => $subscription->currency,
                'status' => 'completed',
                'description' => 'Subscription renewal payment via Geidea',
                'metadata' => [
                    'geidea_order_id' => $orderId,
                    'geidea_subscription_id' => $subscription->geidea_subscription_id,
                ],
                'processed_at' => now(),
            ]);
        });

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
            'auto_renew' => false,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
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
        $alreadyRecorded = SubscriptionEvent::query()
            ->where('subscription_id', $subscription->id)
            ->where('meta->payment_attempt_id', $attempt->id)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $plan = $subscription->plan;
        $event = SubscriptionEvent::create([
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
            'subscription_event_id' => $event->id,
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

    protected function extractSubscriptionId(array $payload, array $order): ?string
    {
        return $order['subscriptionId']
            ?? $payload['subscriptionId']
            ?? $payload['subscription_id']
            ?? null;
    }
}
