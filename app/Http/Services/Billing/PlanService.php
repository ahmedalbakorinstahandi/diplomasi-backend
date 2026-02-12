<?php

namespace App\Http\Services\Billing;

use App\Http\Permissions\Billing\PlanPermission;
use App\Models\Billing\Plan;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;

class PlanService
{
    public function index($filters = [])
    {
        $query = Plan::query()->with([
            // 'subscriptions',
            // 'subscriptionEvents'
        ]);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'created_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = ['name', 'description'];
        $numericFields = ['price'];
        $dateFields = ['created_at'];
        $exactMatchFields = ['interval'];
        $inFields = ['interval'];

        $query = PlanPermission::filterIndex($query);

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
        $plan = Plan::where('id', $id)->first();
        if (!$plan) {
            MessageService::abort(404, 'messages.plan.not_found');
        }

        $plan->load([
            // 'subscriptions',
            // 'subscriptionEvents'
        ]);

        return $plan;
    }

    public function create($data)
    {
        $plan = Plan::create($data);

        // OrderHelper::assign($plan, 'order_index'); // If order_index exists

        $plan = $this->show($plan->id);

        return $plan;
    }

    public function update($data, $plan)
    {
        $plan->update($data);

        $plan = $this->show($plan->id);

        return $plan;
    }

    public function delete($plan)
    {
        // Delete related records if needed
        // Note: Be careful with subscriptions - might want to prevent deletion if active subscriptions exist
        // $plan->subscriptions()->delete();
        // $plan->subscriptionEvents()->delete();

        $plan->delete();
    }

    public function reorder($plan, $validatedData)
    {
        // Reorder functionality if order_index is added to the table
        // OrderHelper::reorder($plan, $validatedData['new_order_index'], 'order_index');

        return $this->show($plan->id);
    }
}
