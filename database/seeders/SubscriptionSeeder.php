<?php

namespace Database\Seeders;

use App\Models\Billing\DiscountCoupon;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionDiscount;
use App\Models\Billing\SubscriptionEvent;
use App\Models\Users\User;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $plans = Plan::query()->get()->values();
        $users = User::query()
            ->where('email', 'like', 'user%@demo.test')
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($plans->isEmpty() || $users->isEmpty()) {
            return;
        }

        $discount = DiscountCoupon::query()->where('code', 'WELCOME10')->first();

        foreach ($users as $idx => $user) {
            $plan = $plans[$idx % $plans->count()];

            $start = now()->subDays(10 + ($idx % 20));
            $end = (clone $start)->addDays($plan->interval === 'annual' ? 365 : 30);

            $subscription = Subscription::withTrashed()->updateOrCreate(
                ['user_id' => $user->id, 'plan_id' => $plan->id],
                [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'status' => $idx % 6 === 0 ? 'expired' : 'active',
                    'price' => $plan->price,
                    'currency' => 'USD',
                    'auto_renew' => $idx % 5 !== 0,
                    'deleted_at' => null,
                ]
            );

            SubscriptionEvent::withTrashed()->updateOrCreate(
                [
                    'subscription_id' => $subscription->id,
                    'event_type' => 'created',
                ],
                [
                    'plan_id' => $plan->id,
                    'status' => $subscription->status,
                    'start_date' => $subscription->start_date,
                    'end_date' => $subscription->end_date,
                    'plan_price' => $plan->price,
                    'amount_charged' => $plan->price,
                    'amount_refunded' => 0,
                    'currency' => 'USD',
                    'meta' => ['source' => 'seed', 'note' => 'initial subscription'],
                    'deleted_at' => null,
                ]
            );

            if ($discount && $idx % 4 === 0) {
                SubscriptionDiscount::withTrashed()->updateOrCreate(
                    [
                        'subscription_id' => $subscription->id,
                        'discount_id' => $discount->id,
                    ],
                    [
                        'discount_type' => $discount->discount_type,
                        'discount_value' => $discount->discount_value,
                        'applied_at' => now(),
                        'deleted_at' => null,
                    ]
                );
            }
        }
    }
}

