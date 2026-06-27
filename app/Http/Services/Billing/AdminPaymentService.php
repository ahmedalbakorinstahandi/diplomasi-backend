<?php

namespace App\Http\Services\Billing;

use App\Http\Permissions\Billing\PaymentPermission;
use App\Models\Billing\PaymentTransaction;
use App\Services\FilterService;
use App\Services\MessageService;

class AdminPaymentService
{
    public function index(array $filters = [])
    {
        $filters = $this->normalizeFilters($filters);

        $query = PaymentTransaction::query()
            ->with(['invoice', 'user', 'plan', 'subscription']);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'created_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('merchant_reference_id', 'like', "%{$search}%")
                    ->orWhere('given_id', 'like', "%{$search}%")
                    ->orWhere('provider_payment_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
            unset($filters['search']);
        }

        $query = PaymentPermission::filterIndex($query);

        return FilterService::applyFilters(
            $query,
            $filters,
            ['merchant_reference_id', 'given_id', 'provider_payment_id', 'gateway_status', 'status', 'currency', 'provider'],
            ['amount_minor', 'display_amount_minor', 'attempt_no', 'plan_id', 'subscription_id', 'user_id'],
            ['billing_period_start', 'billing_period_end', 'finalized_at', 'verified_at', 'next_retry_at', 'created_at'],
            ['provider', 'status', 'gateway_status', 'currency', 'plan_id', 'subscription_id', 'user_id'],
            ['status', 'gateway_status', 'currency', 'provider'],
        );
    }

    public function show(int $id): PaymentTransaction
    {
        PaymentPermission::canShow();

        $payment = PaymentTransaction::with(['invoice', 'user', 'plan', 'subscription'])->find($id);

        if (!$payment) {
            MessageService::abort(404, 'messages.payment.not_found');
        }

        return $payment;
    }

    protected function normalizeFilters(array $filters): array
    {
        if (!empty($filters['sort_by']) && empty($filters['sort_field'])) {
            $filters['sort_field'] = $filters['sort_by'];
        }

        if (!empty($filters['from_date']) && empty($filters['created_at_from'])) {
            $filters['created_at_from'] = $filters['from_date'];
        }

        if (!empty($filters['to_date']) && empty($filters['created_at_to'])) {
            $filters['created_at_to'] = $filters['to_date'];
        }

        return $filters;
    }
}
