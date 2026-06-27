<?php

namespace App\Http\Services\Billing;

use App\Http\Permissions\Billing\SubscriptionPermission;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Services\FilterService;
use App\Services\MessageService;
use Carbon\Carbon;

class AdminSubscriptionService
{
    public function index(array $filters = [])
    {
        $filters = $this->normalizeFilters($filters);

        $query = Subscription::query()->with(['user.roles', 'plan']);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'created_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = [
            ['user.first_name', 'user.last_name'],
            'user.email',
            'user.phone',
        ];
        $numericFields = ['user_id', 'plan_id', 'price'];
        $dateFields = ['created_at', 'start_date', 'end_date'];
        $exactMatchFields = ['status', 'user_id', 'plan_id', 'auto_renew', 'provider'];
        $inFields = ['status', 'provider'];

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })->orWhereHas('plan', function ($planQuery) use ($search) {
                    $planQuery->where('name', 'like', "%{$search}%");
                });
            });
            unset($filters['search']);
        }

        $query = SubscriptionPermission::filterIndex($query);

        return FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );
    }

    public function show(int $id): Subscription
    {
        $subscription = Subscription::with([
            'user.roles',
            'plan',
            'subscriptionEvents' => fn ($q) => $q->with('plan')->orderByDesc('created_at'),
        ])->find($id);

        if (!$subscription) {
            MessageService::abort(404, 'messages.subscription.not_found');
        }

        return $subscription;
    }

    public function create(array $data): Subscription
    {
        $subscription = Subscription::create([
            'user_id' => $data['user_id'],
            'plan_id' => $data['plan_id'],
            'provider' => 'admin',
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => $data['status'],
            'price' => $data['price'],
            'currency' => $data['currency'] ?? 'USD',
            'auto_renew' => $data['auto_renew'] ?? false,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ]);

        return $this->show($subscription->id);
    }

    public function update(array $data, Subscription $subscription): Subscription
    {
        $subscription->update($data);

        return $this->show($subscription->id);
    }

    public function delete(Subscription $subscription): void
    {
        $subscription->delete();
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'auto_renew' => false,
            'cancel_at_period_end' => true,
            'canceled_at' => now(),
            'status' => in_array($subscription->status, ['active', 'past_due'], true)
                ? $subscription->status
                : 'cancelled',
        ]);

        return $this->show($subscription->id);
    }

    public function renew(Subscription $subscription): Subscription
    {
        $plan = $subscription->plan ?? Plan::find($subscription->plan_id);

        if (!$plan) {
            MessageService::abort(404, 'messages.plan.not_found');
        }

        $baseDate = $subscription->end_date && $subscription->end_date->isFuture()
            ? $subscription->end_date->copy()
            : now();

        $newEndDate = $this->calculateEndDate($baseDate, (string) $plan->interval);

        $subscription->update([
            'start_date' => now(),
            'end_date' => $newEndDate,
            'status' => 'active',
            'auto_renew' => true,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'price' => $plan->price,
        ]);

        return $this->show($subscription->id);
    }

    protected function calculateEndDate(Carbon $start, string $interval): Carbon
    {
        $end = $start->copy();

        return match ($interval) {
            'annual' => $end->addYear(),
            'semi_annual' => $end->addMonths(6),
            'quarterly' => $end->addMonths(3),
            default => $end->addMonth(),
        };
    }

    protected function normalizeFilters(array $filters): array
    {
        if (!empty($filters['sort_by']) && empty($filters['sort_field'])) {
            $filters['sort_field'] = $filters['sort_by'];
        }

        if (!empty($filters['sort_order']) && empty($filters['sort_order'])) {
            // already set
        }

        if (!empty($filters['from_date']) && empty($filters['created_at_from'])) {
            $filters['created_at_from'] = $filters['from_date'];
        }

        if (!empty($filters['to_date']) && empty($filters['created_at_to'])) {
            $filters['created_at_to'] = $filters['to_date'];
        }

        if (array_key_exists('auto_renew', $filters) && $filters['auto_renew'] !== null && $filters['auto_renew'] !== '') {
            $filters['auto_renew'] = filter_var($filters['auto_renew'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        return $filters;
    }
}
