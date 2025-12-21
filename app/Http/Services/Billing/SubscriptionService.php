<?php

namespace App\Http\Services\Billing;

use App\Http\Permissions\Billing\SubscriptionPermission;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionEvent;
use App\Services\FilterService;
use App\Services\MessageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
        $subscription = Subscription::create($data);

        $subscription = $this->show($subscription->id);

        return $subscription;
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

            // ============================================================
            // 🔴 TODO: إضافة كود الدفع هنا (Stripe/Payment Gateway)
            // ============================================================
            // هنا يجب إضافة كود الدفع قبل تحديث الاشتراك
            // 
            // مثال Stripe:
            // if ($amountToCharge > 0) {
            //     $paymentIntent = \Stripe\PaymentIntent::create([
            //         'amount' => $amountToCharge * 100, // convert to cents
            //         'currency' => $subscription->currency ?? 'usd',
            //         'customer' => $subscription->user->stripe_customer_id,
            //         'payment_method' => $request->payment_method_id,
            //         'confirm' => true,
            //     ]);
            //     
            //     // أو استخدام Stripe Subscription Update مع proration
            //     // $stripeSubscription = \Stripe\Subscription::update(
            //     //     $subscription->stripe_subscription_id,
            //     //     [
            //     //         'items' => [['plan' => $newPlan->stripe_plan_id]],
            //     //         'proration_behavior' => 'create_prorations',
            //     //     ]
            //     // );
            // }
            // ============================================================

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
            $subscription->end_date = $newEndDate->format('Y-m-d');
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
                // ============================================================
                // 🔴 TODO: إضافة معلومات الدفع هنا بعد تنفيذ الدفع
                // ============================================================
                // 'stripe_invoice_id' => $stripeInvoice->id ?? null,
                // 'stripe_payment_intent_id' => $paymentIntent->id ?? null,
                // 'stripe_charge_id' => $paymentIntent->charges->data[0]->id ?? null,
                // ============================================================
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

