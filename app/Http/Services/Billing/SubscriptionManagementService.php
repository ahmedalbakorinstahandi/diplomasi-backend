<?php

namespace App\Http\Services\Billing;

use App\Models\Billing\Subscription;
use App\Services\MessageService;

class SubscriptionManagementService
{
    public function currentForUser(int $userId): Subscription
    {
        $subscription = $this->getCurrentUserSubscription($userId);

        if (!$subscription) {
            MessageService::abort(404, 'Subscription was not found');
        }

        return $subscription;
    }

    public function cancelAtPeriodEnd(int $userId): Subscription
    {
        $subscription = $this->getManageableUserSubscription($userId);
        if (!$subscription) {
            MessageService::abort(404, 'Active subscription was not found');
        }

        $subscription->update([
            'auto_renew' => false,
            'cancel_at_period_end' => true,
            'canceled_at' => now(),
        ]);

        return $subscription->fresh();
    }

    public function resumeAutoRenew(int $userId): Subscription
    {
        $subscription = $this->getManageableUserSubscription($userId);
        if (!$subscription) {
            MessageService::abort(404, 'Active subscription was not found');
        }

        $subscription->update([
            'auto_renew' => true,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ]);

        return $subscription->fresh();
    }

    protected function getCurrentUserSubscription(int $userId): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['active', 'past_due', 'expired'])
            ->orderByDesc('id')
            ->first();
    }

    protected function getManageableUserSubscription(int $userId): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['active', 'past_due'])
            ->orderByDesc('id')
            ->first();
    }
}

