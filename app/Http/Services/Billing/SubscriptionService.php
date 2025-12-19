<?php

namespace App\Http\Services\Billing;

use App\Http\Permissions\Billing\SubscriptionPermission;
use App\Models\Billing\Subscription;
use App\Services\FilterService;
use App\Services\MessageService;

class SubscriptionService
{
    public function index($filters = [])
    {
        $query = Subscription::query()->with([
            'user',
            'plan',
            'subscriptionEvents',
            'subscriptionDiscounts'
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
            'subscriptionEvents',
            'subscriptionDiscounts'
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
}

