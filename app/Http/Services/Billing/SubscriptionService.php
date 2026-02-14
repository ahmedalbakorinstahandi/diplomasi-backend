<?php

namespace App\Http\Services\Billing;

use App\Http\Permissions\Billing\SubscriptionPermission;
use App\Models\Billing\PaymentAttempt;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionEvent;
use App\Models\Billing\FinancialTransaction;
use App\Services\FilterService;
use App\Services\GeideaService;
use App\Services\MessageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{

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
                'currency' => $data['currency'] ?? 'USD',
                'auto_renew' => $data['auto_renew'] ?? true,
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
            $subscriptionEvent = SubscriptionEvent::create([
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

            // Record financial transaction (local only; no payment gateway)
            if ($amountToCharge > 0) {
                FinancialTransaction::create([
                    'subscription_id' => $subscription->id,
                    'subscription_event_id' => $subscriptionEvent->id ?? null,
                    'user_id' => $subscription->user_id,
                    'type' => 'upgrade_payment',
                    'amount' => $amountToCharge,
                    'currency' => $subscription->currency ?? 'USD',
                    'status' => 'completed',
                    'description' => "Upgrade from {$currentPlan->name} to {$newPlan->name}",
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
     * Cancel auto-renewal (إلغاء الاشتراك). Calls Geidea Cancel Subscription only when geidea_subscription_id exists; current period stays active until end_date. See docs/SUBSCRIPTION_MODEL_FINAL.md
     */
    public function cancelAutoRenew(Subscription $subscription)
    {
        return DB::transaction(function () use ($subscription) {
            if (!empty($subscription->geidea_subscription_id)) {
                $geidea = new GeideaService();
                $geidea->cancelSubscription($subscription->geidea_subscription_id);
            }
            $subscription->update([
                'auto_renew' => false,
            ]);

            return $this->show($subscription->id);
        });
    }

    /**
     * Resume auto-renewal (local only; no payment gateway)
     */
    public function resumeAutoRenew(Subscription $subscription)
    {
        return DB::transaction(function () use ($subscription) {
            $subscription->update([
                'auto_renew' => true,
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

        // No payment gateway: automatic renewal not implemented
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
     * Prepare payment: create PaymentAttempt, Create Subscription at Geidea, Create Session, return session_id and merchant_reference.
     * Per SUBSCRIPTION_MODEL_FINAL: every subscription is with auto-renew; we always call Create Subscription then Create Session with subscriptionId.
     *
     * @param int $planId
     * @param \App\Models\Users\User $user
     * @param bool $autoRenew Ignored; kept for backward compatibility. We always create a Geidea subscription (auto-renew).
     * @return array{session_id: string|null, merchant_reference: string, checkout_url: string|null, hpp_script_url: string, error?: string}
     */
    public function preparePayment(int $planId, $user, bool $autoRenew = true): array
    {
        $plan = Plan::find($planId);
        if (!$plan) {
            MessageService::abort(404, 'messages.plan.not_found');
        }

        $merchantReference = (string) \Illuminate\Support\Str::uuid();
        $amount = (float) $plan->price;
        $currency = config('services.geidea.currency', 'EGP') ?: 'EGP';

        $attempt = PaymentAttempt::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'merchant_reference' => $merchantReference,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'initiated',
            'expires_at' => now()->addMinutes(15),
        ]);

        $geidea = new GeideaService();
        $geideaSubscriptionId = null;

        // Always create Geidea subscription (subscription model: auto-renew by default). See docs/SUBSCRIPTION_MODEL_FINAL.md
        $intervalMap = GeideaService::planIntervalToGeidea($plan->interval ?? 'monthly');
        $customerRequest = [
            'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Customer',
            'email' => $user->email ?? '',
            'phone' => $user->phone ?? '',
            'phoneCountryCode' => $user->phone_country_code ?? '+20',
        ];
        $startDate = now()->format('Y-m-d\TH:i:s.v\Z');
        $result = $geidea->createSubscription([
            'recurring_payment_amount' => $amount,
            'currency' => $currency,
            'cycle_interval' => $intervalMap['cycle_interval'],
            'cycle_frequency' => $intervalMap['cycle_frequency'],
            'type_of_payment' => 'RecurringPayment',
            'customer_request' => $customerRequest,
            'description' => 'Subscription ' . $plan->name,
            'start_date' => $startDate,
            'is_first_pmt_pbl' => false,
        ]);
        if ($result && !empty($result['subscription']['subscriptionId'])) {
            $geideaSubscriptionId = $result['subscription']['subscriptionId'];
            $attempt->geidea_subscription_id = $geideaSubscriptionId;
            $attempt->save();
        }

        $callbackUrl = config('services.geidea.callback_url') ?: url('/api/v1/webhooks/geidea/callback');
        $returnUrl = config('services.geidea.return_url') ?: url('/');

        $sessionResult = $geidea->createSession([
            'amount' => $amount,
            'currency' => $currency,
            'merchant_reference_id' => $merchantReference,
            'callback_url' => $callbackUrl,
            'return_url' => $returnUrl,
            'subscription_id' => $geideaSubscriptionId,
        ]);

        if (!$sessionResult || empty($sessionResult['session']['id'])) {
            $attempt->update(['status' => 'failed', 'failure_reason' => 'Failed to create Geidea session']);
            return [
                'session_id' => null,
                'merchant_reference' => $merchantReference,
                'checkout_url' => null,
                'hpp_script_url' => config('services.geidea.hpp_script_url'),
                'error' => 'Failed to create payment session',
            ];
        }

        $sessionId = $sessionResult['session']['id'];
        $hppBaseUrl = config('services.geidea.hpp_base_url');
        if (empty($hppBaseUrl)) {
            $baseUrl = config('services.geidea.base_url');
            $hppBaseUrl = rtrim(str_replace(['https://api.', 'http://api.'], ['https://www.', 'http://www.'], $baseUrl), '/');
            if (str_contains($baseUrl, 'geidea.ae')) {
                $hppBaseUrl = 'https://payments.geidea.ae';
            }
        }
        $hppBaseUrl = rtrim($hppBaseUrl, '/');
        // Geidea doc: window.open(".../hpp/checkout/?" + sessionId). Session expires in 15 min.
        $checkoutUrl = $hppBaseUrl . '/hpp/checkout/?' . $sessionId;

        $attempt->update([
            'geidea_session_id' => $sessionId,
            'checkout_url' => $checkoutUrl,
            'status' => 'pending',
        ]);

        return [
            'session_id' => $sessionId,
            'merchant_reference' => $merchantReference,
            'checkout_url' => $checkoutUrl,
            'hpp_script_url' => config('services.geidea.hpp_script_url'),
        ];
    }

    /**
     * Get payment attempt status by merchant_reference.
     */
    public function getPaymentStatus(string $merchantReference, $user): ?array
    {
        $attempt = PaymentAttempt::where('merchant_reference', $merchantReference)
            ->where('user_id', $user->id)
            ->first();
        if (!$attempt) {
            return null;
        }
        return [
            'merchant_reference' => $attempt->merchant_reference,
            'status' => $attempt->status,
            'subscription_id' => $attempt->subscription_id,
            'verified_at' => $attempt->verified_at?->toIso8601String(),
        ];
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

