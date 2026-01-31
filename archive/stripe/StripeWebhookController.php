<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Services\Billing\SubscriptionService;
use App\Models\Billing\FinancialTransaction;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionEvent;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    protected StripeService $stripeService;
    protected SubscriptionService $subscriptionService;

    public function __construct(StripeService $stripeService, SubscriptionService $subscriptionService)
    {
        $this->stripeService = $stripeService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Handle Stripe webhook events
     */
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = $this->stripeService->verifyWebhookSignature($payload, $signature);
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        try {
            switch ($event->type) {
                case 'customer.subscription.created':
                    $this->handleSubscriptionCreated($event->data->object);
                    break;

                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;

                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded($event->data->object);
                    break;

                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event->data->object);
                    break;

                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;

                default:
                    Log::info('Unhandled Stripe webhook event', [
                        'type' => $event->type,
                    ]);
            }

            return response()->json(['received' => true], 200);
        } catch (\Exception $e) {
            Log::error('Stripe webhook handling failed', [
                'type' => $event->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Webhook handling failed'], 500);
        }
    }

    /**
     * Handle subscription created
     */
    protected function handleSubscriptionCreated($stripeSubscription)
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $subscription->update([
                'status' => $stripeSubscription->status === 'active' ? 'active' : $subscription->status,
                'current_period_start' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start)->format('Y-m-d'),
                'current_period_end' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)->format('Y-m-d'),
            ]);
        }
    }

    /**
     * Handle subscription updated
     */
    protected function handleSubscriptionUpdated($stripeSubscription)
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $status = match ($stripeSubscription->status) {
                'active' => 'active',
                'canceled' => 'cancelled',
                'past_due' => 'past_due',
                'unpaid' => 'expired',
                default => $subscription->status,
            };

            $subscription->update([
                'status' => $status,
                'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ?? false,
                'current_period_start' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start)->format('Y-m-d'),
                'current_period_end' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)->format('Y-m-d'),
                'end_date' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)->format('Y-m-d'),
            ]);

            if ($stripeSubscription->cancel_at_period_end) {
                $subscription->update([
                    'auto_renew' => false,
                    'canceled_at' => now(),
                ]);
            }
        }
    }

    /**
     * Handle subscription deleted
     */
    protected function handleSubscriptionDeleted($stripeSubscription)
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $subscription->update([
                'status' => 'cancelled',
                'auto_renew' => false,
                'canceled_at' => now(),
            ]);
        }
    }

    /**
     * Handle invoice payment succeeded
     */
    protected function handleInvoicePaymentSucceeded($invoice)
    {
        if (!isset($invoice->subscription)) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $invoice->subscription)->first();

        if ($subscription) {
            // Create renewal event
            SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'event_type' => 'renewed',
                'plan_id' => $subscription->plan_id,
                'status' => 'active',
                'start_date' => \Carbon\Carbon::createFromTimestamp($invoice->period_start)->format('Y-m-d'),
                'end_date' => \Carbon\Carbon::createFromTimestamp($invoice->period_end)->format('Y-m-d'),
                'plan_price' => $subscription->price,
                'amount_charged' => $invoice->amount_paid / 100,
                'amount_refunded' => 0,
                'currency' => strtoupper($invoice->currency),
                'stripe_invoice_id' => $invoice->id,
                'stripe_payment_intent_id' => $invoice->payment_intent ?? null,
                'meta' => [
                    'auto_renew' => true,
                    'renewal_type' => 'automatic',
                ],
                'created_at' => now(),
            ]);

            // Record financial transaction
            FinancialTransaction::updateOrCreate(
                [
                    'stripe_invoice_id' => $invoice->id,
                ],
                [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'type' => 'subscription_payment',
                    'amount' => $invoice->amount_paid / 100,
                    'currency' => strtoupper($invoice->currency),
                    'status' => 'completed',
                    'stripe_invoice_id' => $invoice->id,
                    'stripe_payment_intent_id' => $invoice->payment_intent ?? null,
                    'description' => "Subscription renewal payment",
                    'processed_at' => now(),
                ]
            );

            // Update subscription dates
            $subscription->update([
                'current_period_start' => \Carbon\Carbon::createFromTimestamp($invoice->period_start)->format('Y-m-d'),
                'current_period_end' => \Carbon\Carbon::createFromTimestamp($invoice->period_end)->format('Y-m-d'),
                'end_date' => \Carbon\Carbon::createFromTimestamp($invoice->period_end)->format('Y-m-d'),
                'status' => 'active',
            ]);
        }
    }

    /**
     * Handle invoice payment failed
     */
    protected function handleInvoicePaymentFailed($invoice)
    {
        if (!isset($invoice->subscription)) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $invoice->subscription)->first();

        if ($subscription) {
            $subscription->update([
                'status' => 'past_due',
            ]);

            // Record failed transaction
            FinancialTransaction::updateOrCreate(
                [
                    'stripe_invoice_id' => $invoice->id,
                ],
                [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'type' => 'subscription_payment',
                    'amount' => $invoice->amount_due / 100,
                    'currency' => strtoupper($invoice->currency),
                    'status' => 'failed',
                    'stripe_invoice_id' => $invoice->id,
                    'description' => "Subscription payment failed",
                ]
            );
        }
    }

    /**
     * Handle payment intent succeeded
     */
    protected function handlePaymentIntentSucceeded($paymentIntent)
    {
        // Update transaction status if exists
        FinancialTransaction::where('stripe_payment_intent_id', $paymentIntent->id)
            ->update([
                'status' => 'completed',
                'stripe_charge_id' => $paymentIntent->charges->data[0]->id ?? null,
                'processed_at' => now(),
            ]);
    }

    /**
     * Handle payment intent failed
     */
    protected function handlePaymentIntentFailed($paymentIntent)
    {
        // Update transaction status if exists
        FinancialTransaction::where('stripe_payment_intent_id', $paymentIntent->id)
            ->update([
                'status' => 'failed',
            ]);
    }
}
