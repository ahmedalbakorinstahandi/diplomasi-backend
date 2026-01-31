<?php

namespace App\Http\Services\Billing;

use App\Http\Permissions\Billing\SubscriptionPermission;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionEvent;
use App\Models\Billing\FinancialTransaction;
use App\Models\Billing\PaymentAttempt;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    public function index($filters = [])
    {
        $query = Subscription::query()->with([
            'user',
            'plan',
            // 'subscriptionEvents',
            // 'subscriptionDiscounts'
        ]);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'created_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = [];
        $numericFields = ['price'];
        $dateFields = ['start_date', 'end_date', 'created_at'];
        $exactMatchFields = ['user_id', 'plan_id', 'status'];
        $inFields = ['status'];

        $query = SubscriptionPermission::filterIndex($query);

        $query = FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );

        return $query;
    }

    public function show(int $id)
    {
        $subscription = Subscription::where('id', $id)->first();
        if (!$subscription) {
            MessageService::abort(404, 'messages.subscription.not_found');
        }

        $subscription->load([
            'user',
            'plan',
            // 'subscriptionEvents',
            // 'subscriptionDiscounts'
        ]);

        return $subscription;
    }

    public function create($data)
    {
        return DB::transaction(function () use ($data) {
            // Get plan
            $plan = Plan::find($data['plan_id']);
            if (!$plan) {
                MessageService::abort(404, 'messages.plan.not_found');
            }

            // Get user_id from data (should be passed from controller or request)
            // في Admin routes: يتم إرسال user_id في request
            // في User routes: يمكن أخذه من authenticated user
            $userId = $data['user_id'] ?? null;
            if (!$userId) {
                MessageService::abort(400, 'messages.subscription.user_id_required');
            }

            // Calculate dates
            $now = Carbon::now();
            $startDate = $now->copy();
            $intervalDays = $this->getIntervalDays($plan->interval);
            $endDate = $now->copy()->addDays($intervalDays);

            // Prepare subscription data
            $subscriptionData = [
                'user_id' => $userId,
                'plan_id' => $plan->id,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'status' => 'active',
                'price' => $plan->price,
                'currency' => 'USD', // Default currency
                'stripe_subscription_id' => $data['stripe_subscription_id'] ?? null,
                'auto_renew' => $data['auto_renew'] ?? true, // Default to true
            ];

            // Create subscription
            $subscription = Subscription::create($subscriptionData);

            // Create subscription event
            SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'event_type' => 'created',
                'plan_id' => $plan->id,
                'status' => 'active',
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'plan_price' => $plan->price,
                'amount_charged' => $plan->price,
                'amount_refunded' => 0,
                'currency' => $subscription->currency,
                // ============================================================
                // 🔴 TODO: إضافة معلومات الدفع هنا بعد تنفيذ الدفع
                // ============================================================
                // 'stripe_invoice_id' => $stripeInvoice->id ?? null,
                // 'stripe_payment_intent_id' => $paymentIntent->id ?? null,
                // 'stripe_charge_id' => $paymentIntent->charges->data[0]->id ?? null,
                // ============================================================
                'meta' => [
                    'auto_renew' => $subscription->auto_renew,
                    'created_via' => 'api',
                ],
                'created_at' => now(),
            ]);

            return $this->show($subscription->id);
        });
    }

    public function update($data, $subscription)
    {
        $subscription->update($data);

        $subscription = $this->show($subscription->id);

        return $subscription;
    }

    public function delete($subscription)
    {
        $subscription->subscriptionEvents()->delete();
        $subscription->subscriptionDiscounts()->delete();
        $subscription->delete();
    }

    public function cancel($subscription, $reason = null)
    {
        $subscription->status = 'cancelled';
        $subscription->save();

        return $subscription;
    }

    public function renew($subscription)
    {
        // Renew logic - extend end_date based on plan duration
        $subscription->status = 'active';
        $subscription->save();

        return $subscription;
    }

    /**
     * Upgrade subscription with Pro-Rated calculation
     * 
     * @param Subscription $subscription
     * @param int $newPlanId
     * @return Subscription
     */
    public function upgradeSubscription(Subscription $subscription, int $newPlanId)
    {
        // Check if subscription is active
        if ($subscription->status !== 'active') {
            MessageService::abort(400, 'messages.subscription.must_be_active');
        }

        // Get new plan
        $newPlan = Plan::find($newPlanId);
        if (!$newPlan) {
            MessageService::abort(404, 'messages.plan.not_found');
        }

        // Prevent upgrading to same plan
        if ($subscription->plan_id === $newPlanId) {
            MessageService::abort(400, 'messages.subscription.same_plan');
        }

        // Get current plan
        $currentPlan = $subscription->plan;

        // Check if new plan price is higher (upgrade only, not downgrade)
        if ($newPlan->price <= $currentPlan->price) {
            MessageService::abort(400, 'messages.subscription.must_upgrade_to_higher_plan');
        }

        return DB::transaction(function () use ($subscription, $newPlan, $currentPlan) {
            // Get last payment event (created or renewed) to get actual paid amount
            $lastPaymentEvent = $subscription->subscriptionEvents()
                ->whereIn('event_type', ['created', 'renewed'])
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastPaymentEvent) {
                MessageService::abort(400, 'messages.subscription.no_payment_event_found');
            }

            // Calculate actual paid amount (amount_charged - amount_refunded)
            $paidAmount = ($lastPaymentEvent->amount_charged ?? 0) - ($lastPaymentEvent->amount_refunded ?? 0);
            
            // Use subscription price as fallback if no event amount
            if ($paidAmount <= 0) {
                $paidAmount = $subscription->price ?? $lastPaymentEvent->plan_price ?? 0;
            }

            if ($paidAmount <= 0) {
                MessageService::abort(400, 'messages.subscription.invalid_paid_amount');
            }

            // Calculate period dates
            $periodStart = $lastPaymentEvent->start_date ?? $subscription->start_date;
            $periodEnd = $lastPaymentEvent->end_date ?? $subscription->end_date;
            $now = Carbon::now();

            // Validate dates
            if (!$periodStart || !$periodEnd) {
                MessageService::abort(400, 'messages.subscription.invalid_period_dates');
            }

            $periodStart = Carbon::parse($periodStart);
            $periodEnd = Carbon::parse($periodEnd);

            // Calculate remaining time
            if ($periodEnd->isPast()) {
                MessageService::abort(400, 'messages.subscription.subscription_expired');
            }

            // Calculate total and remaining seconds for precision
            $totalSeconds = $periodStart->diffInSeconds($periodEnd);
            $remainingSeconds = max(0, $now->diffInSeconds($periodEnd, false));

            if ($totalSeconds <= 0) {
                MessageService::abort(400, 'messages.subscription.invalid_period');
            }

            // Calculate credit (remaining value)
            $remainingRatio = $remainingSeconds / $totalSeconds;
            $credit = $paidAmount * $remainingRatio;

            // Get new plan price (current price from plan table)
            $newPlanPrice = $newPlan->price;

            // Calculate amount to charge (new price - credit)
            $amountToCharge = max(0, $newPlanPrice - $credit);

            $stripeInvoiceId = null;
            $stripePaymentIntentId = null;
            $stripeChargeId = null;

            // Update Stripe subscription if exists
            if ($subscription->stripe_subscription_id) {
                try {
                    // Get or create Stripe Price
                    $stripePriceId = $this->stripeService->getOrCreatePrice(
                        $newPlan->stripe_plan_id,
                        $newPlan->price,
                        $subscription->currency ?? 'USD',
                        $newPlan->interval,
                        $newPlan->name
                    );

                    $stripeSubscription = $this->stripeService->updateSubscription(
                        $subscription->stripe_subscription_id,
                        [
                            'price_id' => $stripePriceId,
                            'proration_behavior' => 'create_prorations',
                        ]
                    );

                    // Get invoice and payment intent from latest invoice
                    if (isset($stripeSubscription->latest_invoice)) {
                        $invoice = $stripeSubscription->latest_invoice;
                        $stripeInvoiceId = $invoice->id ?? null;
                        $stripePaymentIntentId = $invoice->payment_intent->id ?? null;
                        $stripeChargeId = $invoice->payment_intent->charges->data[0]->id ?? null;
                    }
                } catch (\Exception $e) {
                    Log::error('Stripe subscription upgrade failed', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with local update even if Stripe fails
                }
            } elseif ($amountToCharge > 0) {
                // If no Stripe subscription, create payment intent
                $customerId = $subscription->user->getStripeCustomer();
                $paymentMethodId = $subscription->stripe_payment_method_id ?? $subscription->user->stripe_default_payment_method_id;
                
                if ($paymentMethodId) {
                    try {
                        $paymentIntent = $this->stripeService->createPaymentIntent(
                            $amountToCharge,
                            $subscription->currency ?? 'USD',
                            $customerId,
                            $paymentMethodId,
                            [
                                'subscription_id' => $subscription->id,
                                'upgrade' => 'true',
                                'old_plan_id' => $currentPlan->id,
                                'new_plan_id' => $newPlan->id,
                            ]
                        );
                        $stripePaymentIntentId = $paymentIntent->id;
                        $stripeChargeId = $paymentIntent->charges->data[0]->id ?? null;
                    } catch (\Exception $e) {
                        Log::error('Payment intent creation failed for upgrade', [
                            'subscription_id' => $subscription->id,
                            'error' => $e->getMessage(),
                        ]);
                        MessageService::abort(400, 'Payment processing failed');
                    }
                }
            }

            // Calculate new end date based on new plan interval
            $intervalDays = $this->getIntervalDays($newPlan->interval);
            $newEndDate = $now->copy()->addDays($intervalDays);

            // If same interval type, preserve original end_date, otherwise use calculated
            // For upgrade to higher tier with same interval, extend from now
            // For upgrade to different interval (monthly -> annual), use new calculation
            if ($currentPlan->interval === $newPlan->interval) {
                // Same interval: keep end_date (just change plan)
                $newEndDate = $periodEnd;
            }
            // Otherwise, use calculated end_date (new interval period from now)

            // Store old values for event
            $oldPlanId = $subscription->plan_id;
            $oldPrice = $subscription->price;
            $oldEndDate = $subscription->end_date;

            // Update subscription
            $subscription->plan_id = $newPlan->id;
            $subscription->price = $newPlanPrice;
            $subscription->end_date = $newEndDate->toDateString();
            $subscription->status = 'active';
            $subscription->save();

            // Create upgrade event
            SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'event_type' => 'upgraded',
                'plan_id' => $newPlan->id,
                'status' => 'active',
                'start_date' => $subscription->start_date,
                'end_date' => $newEndDate->format('Y-m-d'),
                'plan_price' => $newPlanPrice,
                'amount_charged' => $amountToCharge,
                'amount_refunded' => 0,
                'currency' => $subscription->currency ?? 'USD',
                'stripe_invoice_id' => $stripeInvoiceId,
                'stripe_payment_intent_id' => $stripePaymentIntentId,
                'stripe_charge_id' => $stripeChargeId,
                'meta' => [
                    'old_plan_id' => $oldPlanId,
                    'old_price' => $oldPrice,
                    'old_end_date' => $oldEndDate ? (is_string($oldEndDate) ? $oldEndDate : Carbon::parse($oldEndDate)->format('Y-m-d')) : null,
                    'new_plan_price' => $newPlanPrice,
                    'paid_amount' => $paidAmount,
                    'remaining_ratio' => round($remainingRatio, 4),
                    'remaining_seconds' => $remainingSeconds,
                    'total_seconds' => $totalSeconds,
                    'credit' => round($credit, 2),
                    'period_start' => $periodStart->format('Y-m-d'),
                    'period_end' => $periodEnd->format('Y-m-d'),
                ],
                'created_at' => now(),
            ]);

            // Record financial transaction
            if ($amountToCharge > 0) {
                FinancialTransaction::create([
                    'subscription_id' => $subscription->id,
                    'subscription_event_id' => $subscriptionEvent->id ?? null,
                    'user_id' => $subscription->user_id,
                    'type' => 'upgrade_payment',
                    'amount' => $amountToCharge,
                    'currency' => $subscription->currency ?? 'USD',
                    'status' => 'completed',
                    'stripe_payment_intent_id' => $stripePaymentIntentId,
                    'stripe_invoice_id' => $stripeInvoiceId,
                    'stripe_charge_id' => $stripeChargeId,
                    'description' => "Upgrade payment from {$currentPlan->name} to {$newPlan->name}",
                    'metadata' => [
                        'old_plan_id' => $oldPlanId,
                        'new_plan_id' => $newPlan->id,
                        'credit_applied' => $credit,
                        'paid_amount' => $paidAmount,
                    ],
                    'processed_at' => now(),
                ]);
            }

            return $this->show($subscription->id);
        });
    }

    /**
     * Create subscription with payment intent (after payment confirmed in Flutter)
     */
    public function createWithPaymentIntent($data, $user)
    {
        return DB::transaction(function () use ($data, $user) {
            // Get plan
            $plan = Plan::find($data['plan_id']);
            if (!$plan) {
                MessageService::abort(404, 'messages.plan.not_found');
            }

            // Get payment intent
            $paymentIntentId = $data['payment_intent_id'] ?? null;
            if (!$paymentIntentId) {
                MessageService::abort(400, 'Payment intent ID is required');
            }

            // Retrieve payment intent from Stripe
            $paymentIntent = $this->stripeService->getClient()->paymentIntents->retrieve($paymentIntentId);
            
            if ($paymentIntent->status !== 'succeeded') {
                MessageService::abort(400, 'Payment intent not succeeded');
            }

            $customerId = $paymentIntent->customer;
            $paymentMethodId = $paymentIntent->payment_method;

            // Get or create Stripe Price
            $stripePriceId = $this->stripeService->getOrCreatePrice(
                $plan->stripe_plan_id,
                $plan->price,
                'USD',
                $plan->interval,
                $plan->name
            );

            // Create Stripe subscription
            $stripeSubscription = $this->stripeService->createSubscription(
                $customerId,
                $stripePriceId,
                $paymentMethodId,
                [
                    'metadata' => [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'app' => 'diplomasi',
                    ],
                ]
            );

            // Calculate dates
            $now = Carbon::now();
            $startDate = $now->copy();
            $intervalDays = $this->getIntervalDays($plan->interval);
            $endDate = $now->copy()->addDays($intervalDays);

            // Get period dates from Stripe subscription if available, otherwise use calculated dates
            $currentPeriodStart = isset($stripeSubscription->current_period_start) && $stripeSubscription->current_period_start !== null
                ? Carbon::createFromTimestamp($stripeSubscription->current_period_start)->toDateString()
                : $startDate->toDateString();

            $currentPeriodEnd = isset($stripeSubscription->current_period_end) && $stripeSubscription->current_period_end !== null
                ? Carbon::createFromTimestamp($stripeSubscription->current_period_end)->toDateString()
                : $endDate->toDateString();

            // Prepare subscription data
            $subscriptionData = [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => 'active',
                'price' => $plan->price,
                'currency' => 'USD',
                'stripe_subscription_id' => $stripeSubscription->id,
                'stripe_customer_id' => $customerId,
                'stripe_payment_method_id' => $paymentMethodId,
                'auto_renew' => $data['auto_renew'] ?? true,
                'current_period_start' => $currentPeriodStart,
                'current_period_end' => $currentPeriodEnd,
            ];

            // Create subscription
            $subscription = Subscription::create($subscriptionData);

            // Create subscription event
            $subscriptionEvent = SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'event_type' => 'created',
                'plan_id' => $plan->id,
                'status' => 'active',
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'plan_price' => $plan->price,
                'amount_charged' => $plan->price,
                'amount_refunded' => 0,
                'currency' => $subscription->currency,
                'stripe_invoice_id' => $stripeSubscription->latest_invoice->id ?? null,
                'stripe_payment_intent_id' => $paymentIntentId,
                'meta' => [
                    'auto_renew' => $subscription->auto_renew,
                    'created_via' => 'api',
                    'stripe_subscription_id' => $stripeSubscription->id,
                ],
                'created_at' => now(),
            ]);

            // Record financial transaction
            FinancialTransaction::create([
                'subscription_id' => $subscription->id,
                'subscription_event_id' => $subscriptionEvent->id,
                'user_id' => $user->id,
                'type' => 'subscription_payment',
                'amount' => $plan->price,
                'currency' => 'USD',
                'status' => 'completed',
                'stripe_payment_intent_id' => $paymentIntentId,
                'stripe_invoice_id' => $stripeSubscription->latest_invoice->id ?? null,
                'description' => "Subscription payment for plan: {$plan->name}",
                'processed_at' => now(),
            ]);

            return $this->show($subscription->id);
        });
    }

    /**
     * Create subscription with payment (legacy method - kept for compatibility)
     */
    public function createWithPayment($data, $user)
    {
        return DB::transaction(function () use ($data, $user) {
            // Get plan
            $plan = Plan::find($data['plan_id']);
            if (!$plan) {
                MessageService::abort(404, 'messages.plan.not_found');
            }

            // Get or create Stripe customer
            $customerId = $user->getStripeCustomer();

            // Payment method ID is required
            $paymentMethodId = $data['payment_method_id'] ?? null;
            if (!$paymentMethodId) {
                MessageService::abort(400, 'Payment method is required');
            }

            // Get or create Stripe Price
            $stripePriceId = $this->stripeService->getOrCreatePrice(
                $plan->stripe_plan_id,
                $plan->price,
                'USD',
                $plan->interval,
                $plan->name
            );

            // Create Stripe subscription
            $stripeSubscription = $this->stripeService->createSubscription(
                $customerId,
                $stripePriceId,
                $paymentMethodId,
                [
                    'metadata' => [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'app' => 'diplomasi',
                    ],
                ]
            );

            // Calculate dates
            $now = Carbon::now();
            $startDate = $now->copy();
            $intervalDays = $this->getIntervalDays($plan->interval);
            $endDate = $now->copy()->addDays($intervalDays);

            // Prepare subscription data
            $subscriptionData = [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'status' => 'active',
                'price' => $plan->price,
                'currency' => 'USD',
                'stripe_subscription_id' => $stripeSubscription->id,
                'stripe_customer_id' => $customerId,
                'stripe_payment_method_id' => $paymentMethodId,
                'auto_renew' => $data['auto_renew'] ?? true,
                'current_period_start' => Carbon::createFromTimestamp($stripeSubscription->current_period_start)->format('Y-m-d'),
                'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end)->format('Y-m-d'),
            ];

            // Create subscription
            $subscription = Subscription::create($subscriptionData);

            // Create subscription event
            $subscriptionEvent = SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'event_type' => 'created',
                'plan_id' => $plan->id,
                'status' => 'active',
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'plan_price' => $plan->price,
                'amount_charged' => $plan->price,
                'amount_refunded' => 0,
                'currency' => $subscription->currency,
                'stripe_invoice_id' => $stripeSubscription->latest_invoice->id ?? null,
                'stripe_payment_intent_id' => $stripeSubscription->latest_invoice->payment_intent->id ?? null,
                'meta' => [
                    'auto_renew' => $subscription->auto_renew,
                    'created_via' => 'api',
                    'stripe_subscription_id' => $stripeSubscription->id,
                ],
                'created_at' => now(),
            ]);

            // Record financial transaction
            FinancialTransaction::create([
                'subscription_id' => $subscription->id,
                'subscription_event_id' => $subscriptionEvent->id,
                'user_id' => $user->id,
                'type' => 'subscription_payment',
                'amount' => $plan->price,
                'currency' => 'USD',
                'status' => 'completed',
                'stripe_payment_intent_id' => $stripeSubscription->latest_invoice->payment_intent->id ?? null,
                'stripe_invoice_id' => $stripeSubscription->latest_invoice->id ?? null,
                'description' => "Subscription payment for plan: {$plan->name}",
                'processed_at' => now(),
            ]);

            return $this->show($subscription->id);
        });
    }

    /**
     * Cancel auto-renewal
     */
    public function cancelAutoRenew(Subscription $subscription)
    {
        if (!$subscription->stripe_subscription_id) {
            MessageService::abort(400, 'Subscription does not have Stripe ID');
        }

        return DB::transaction(function () use ($subscription) {
            // Update Stripe
            $this->stripeService->cancelSubscription($subscription->stripe_subscription_id, true);

            // Update local subscription
            $subscription->update([
                'auto_renew' => false,
                'cancel_at_period_end' => true,
                'canceled_at' => now(),
            ]);

            return $this->show($subscription->id);
        });
    }

    /**
     * Resume auto-renewal
     */
    public function resumeAutoRenew(Subscription $subscription)
    {
        if (!$subscription->stripe_subscription_id) {
            MessageService::abort(400, 'Subscription does not have Stripe ID');
        }

        return DB::transaction(function () use ($subscription) {
            // Update Stripe
            $this->stripeService->resumeSubscription($subscription->stripe_subscription_id);

            // Update local subscription
            $subscription->update([
                'auto_renew' => true,
                'cancel_at_period_end' => false,
                'canceled_at' => null,
            ]);

            return $this->show($subscription->id);
        });
    }

    /**
     * Pause subscription (Admin only)
     */
    public function pause(Subscription $subscription, $reason = null)
    {
        return DB::transaction(function () use ($subscription, $reason) {
            $oldStatus = $subscription->status;

            $subscription->update([
                'status' => 'cancelled',
            ]);

            // Record financial transaction for transparency
            FinancialTransaction::create([
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'type' => 'admin_adjustment',
                'amount' => 0,
                'currency' => $subscription->currency,
                'status' => 'completed',
                'description' => "Subscription paused by admin. Reason: " . ($reason ?? 'No reason provided'),
                'metadata' => [
                    'action' => 'pause',
                    'old_status' => $oldStatus,
                    'new_status' => 'cancelled',
                    'reason' => $reason,
                    'admin_id' => \App\Models\Users\User::auth()?->id,
                ],
                'processed_at' => now(),
            ]);

            return $this->show($subscription->id);
        });
    }

    /**
     * Resume subscription (Admin only)
     */
    public function resume(Subscription $subscription)
    {
        return DB::transaction(function () use ($subscription) {
            $oldStatus = $subscription->status;

            $subscription->update([
                'status' => 'active',
            ]);

            // Record financial transaction for transparency
            FinancialTransaction::create([
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'type' => 'admin_adjustment',
                'amount' => 0,
                'currency' => $subscription->currency,
                'status' => 'completed',
                'description' => "Subscription resumed by admin",
                'metadata' => [
                    'action' => 'resume',
                    'old_status' => $oldStatus,
                    'new_status' => 'active',
                    'admin_id' => \App\Models\Users\User::auth()?->id,
                ],
                'processed_at' => now(),
            ]);

            return $this->show($subscription->id);
        });
    }

    /**
     * Manual renewal (Admin only, without payment)
     */
    public function renewManual(Subscription $subscription, $days = null)
    {
        return DB::transaction(function () use ($subscription, $days) {
            $plan = $subscription->plan;
            $intervalDays = $days ?? $this->getIntervalDays($plan->interval);

            $oldEndDate = $subscription->end_date;
            $newEndDate = Carbon::parse($subscription->end_date)->addDays($intervalDays);

            $subscription->update([
                'status' => 'active',
                'end_date' => $newEndDate->format('Y-m-d'),
                'current_period_end' => $newEndDate->format('Y-m-d'),
            ]);

            // Create renewal event
            SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'event_type' => 'renewed',
                'plan_id' => $plan->id,
                'status' => 'active',
                'start_date' => $subscription->start_date,
                'end_date' => $newEndDate->format('Y-m-d'),
                'plan_price' => $plan->price,
                'amount_charged' => 0, // Manual renewal, no charge
                'amount_refunded' => 0,
                'currency' => $subscription->currency,
                'meta' => [
                    'renewal_type' => 'manual',
                    'old_end_date' => $oldEndDate,
                    'days_added' => $intervalDays,
                    'admin_id' => \App\Models\Users\User::auth()?->id,
                ],
                'created_at' => now(),
            ]);

            // Record financial transaction for transparency
            FinancialTransaction::create([
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'type' => 'admin_adjustment',
                'amount' => 0,
                'currency' => $subscription->currency,
                'status' => 'completed',
                'description' => "Subscription manually renewed by admin. Extended by {$intervalDays} days.",
                'metadata' => [
                    'action' => 'renew_manual',
                    'old_end_date' => $oldEndDate,
                    'new_end_date' => $newEndDate->format('Y-m-d'),
                    'days_added' => $intervalDays,
                    'admin_id' => \App\Models\Users\User::auth()?->id,
                ],
                'processed_at' => now(),
            ]);

            return $this->show($subscription->id);
        });
    }

    /**
     * Extend subscription (Admin only, without payment)
     */
    public function extend(Subscription $subscription, $days)
    {
        return DB::transaction(function () use ($subscription, $days) {
            $oldEndDate = $subscription->end_date;
            $newEndDate = Carbon::parse($subscription->end_date)->addDays($days);

            $subscription->update([
                'end_date' => $newEndDate->format('Y-m-d'),
                'current_period_end' => $newEndDate->format('Y-m-d'),
            ]);

            // Record financial transaction for transparency
            FinancialTransaction::create([
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'type' => 'admin_adjustment',
                'amount' => 0,
                'currency' => $subscription->currency,
                'status' => 'completed',
                'description' => "Subscription extended by admin. Extended by {$days} days.",
                'metadata' => [
                    'action' => 'extend',
                    'old_end_date' => $oldEndDate,
                    'new_end_date' => $newEndDate->format('Y-m-d'),
                    'days_added' => $days,
                    'admin_id' => \App\Models\Users\User::auth()?->id,
                ],
                'processed_at' => now(),
            ]);

            return $this->show($subscription->id);
        });
    }

    /**
     * Get current subscription for user
     */
    public function getCurrent($user)
    {
        return Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->with(['plan', 'user'])
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Process automatic renewal
     */
    public function processAutomaticRenewal(Subscription $subscription)
    {
        if (!$subscription->auto_renew) {
            return false;
        }

        if ($subscription->status !== 'active') {
            return false;
        }

        // Check if subscription is about to expire (within 3 days)
        $daysUntilExpiry = Carbon::parse($subscription->end_date)->diffInDays(now(), false);
        if ($daysUntilExpiry > 3) {
            return false;
        }

        // Stripe handles automatic renewal, we just need to sync
        if ($subscription->stripe_subscription_id) {
            try {
                $stripeSubscription = $this->stripeService->getSubscription($subscription->stripe_subscription_id);
                
                // Update local subscription based on Stripe data
                $subscription->update([
                    'current_period_start' => Carbon::createFromTimestamp($stripeSubscription->current_period_start)->format('Y-m-d'),
                    'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end)->format('Y-m-d'),
                    'end_date' => Carbon::createFromTimestamp($stripeSubscription->current_period_end)->format('Y-m-d'),
                    'status' => $stripeSubscription->status === 'active' ? 'active' : $subscription->status,
                ]);

                return true;
            } catch (\Exception $e) {
                Log::error('Failed to sync subscription renewal', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }
        }

        return false;
    }

    /**
     * Get user subscriptions list (for user routes)
     * 
     * @param \App\Models\Users\User $user
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getUserSubscriptions($user, $filters = [])
    {
        $query = Subscription::query()
            ->where('user_id', $user->id)
            ->with(['plan']);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'created_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = [];
        $numericFields = ['price'];
        $dateFields = ['start_date', 'end_date', 'created_at'];
        $exactMatchFields = ['plan_id', 'status'];
        $inFields = ['status'];

        $query = FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );

        return $query;
    }

    /**
     * Get user subscription details (for user routes)
     * 
     * @param \App\Models\Users\User $user
     * @param int $id
     * @return \App\Models\Billing\Subscription
     */
    public function getUserSubscription($user, int $id)
    {
        $subscription = Subscription::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$subscription) {
            MessageService::abort(404, 'messages.subscription.not_found');
        }

        $subscription->load([
            'plan',
            'subscriptionEvents' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
        ]);

        return $subscription;
    }

    /**
     * Create subscription from PaymentAttempt (idempotent).
     * 
     * @param PaymentAttempt $attempt
     * @param array $options
     * @return Subscription
     */
    public function createFromPaymentAttempt(PaymentAttempt $attempt, array $options = []): Subscription
    {
        // Validate attempt status
        if ($attempt->status !== 'completed') {
            MessageService::abort(400, 'Payment attempt must be completed to create subscription');
        }

        // Idempotency check: if subscription already exists, return it
        if ($attempt->subscription_id) {
            $existingSubscription = Subscription::find($attempt->subscription_id);
            if ($existingSubscription) {
                Log::info('Subscription already exists for PaymentAttempt', [
                    'payment_attempt_id' => $attempt->id,
                    'subscription_id' => $existingSubscription->id,
                ]);
                return $existingSubscription;
            }
        }

        return DB::transaction(function () use ($attempt, $options) {
            // Get plan
            $plan = $attempt->plan;
            if (!$plan) {
                MessageService::abort(404, 'Plan not found for payment attempt');
            }

            // Calculate dates
            $now = Carbon::now();
            $startDate = $now->copy();
            $intervalDays = $this->getIntervalDays($plan->interval);
            $endDate = $now->copy()->addDays($intervalDays);

            // Prepare subscription data
            $subscriptionData = [
                'user_id' => $attempt->user_id,
                'plan_id' => $plan->id,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'status' => 'active',
                'price' => $attempt->amount,
                'currency' => $attempt->currency ?? 'SAR',
                'auto_renew' => $options['auto_renew'] ?? true,
            ];

            // Create subscription
            $subscription = Subscription::create($subscriptionData);

            // Link subscription to payment attempt
            $attempt->update(['subscription_id' => $subscription->id]);

            // Create subscription event
            $subscriptionEvent = SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'event_type' => 'created',
                'plan_id' => $plan->id,
                'status' => 'active',
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'plan_price' => $attempt->amount,
                'amount_charged' => $attempt->amount,
                'amount_refunded' => 0,
                'currency' => $subscription->currency,
                'meta' => [
                    'auto_renew' => $subscription->auto_renew,
                    'created_via' => 'geidea_payment',
                    'payment_attempt_id' => $attempt->id,
                    'merchant_reference' => $attempt->merchant_reference,
                    'geidea_order_id' => $attempt->geidea_order_id,
                ],
                'created_at' => now(),
            ]);

            // Record financial transaction
            FinancialTransaction::create([
                'subscription_id' => $subscription->id,
                'subscription_event_id' => $subscriptionEvent->id,
                'user_id' => $attempt->user_id,
                'type' => 'subscription_payment',
                'amount' => $attempt->amount,
                'currency' => $attempt->currency ?? 'SAR',
                'status' => 'completed',
                'description' => "Subscription payment for plan: {$plan->name}",
                'metadata' => [
                    'payment_attempt_id' => $attempt->id,
                    'merchant_reference' => $attempt->merchant_reference,
                    'geidea_order_id' => $attempt->geidea_order_id,
                ],
                'processed_at' => now(),
            ]);

            Log::info('Subscription created from PaymentAttempt', [
                'payment_attempt_id' => $attempt->id,
                'subscription_id' => $subscription->id,
                'merchant_reference' => $attempt->merchant_reference,
            ]);

            return $this->show($subscription->id);
        });
    }

    /**
     * Get number of days for an interval
     * 
     * @param string $interval
     * @return int
     */
    private function getIntervalDays(string $interval): int
    {
        return match ($interval) {
            'monthly' => 30,
            'semi_annual' => 180, // 6 months
            'annual' => 365,
            default => 30,
        };
    }
}

