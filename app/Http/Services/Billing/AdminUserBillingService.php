<?php

namespace App\Http\Services\Billing;

use App\Http\Permissions\Billing\InvoicePermission;
use App\Http\Permissions\Billing\PaymentPermission;
use App\Http\Permissions\Billing\SubscriptionPermission;
use App\Models\Billing\SubscriptionEvent;
use App\Models\Users\User;
use App\Services\MessageService;

class AdminUserBillingService
{
    public function __construct(
        protected AdminSubscriptionService $subscriptionService,
        protected AdminPaymentService $paymentService,
        protected AdminInvoiceService $invoiceService,
    ) {}

    public function billingSummary(int $userId, array $filters = []): array
    {
        SubscriptionPermission::canView();
        PaymentPermission::canView();
        InvoicePermission::canView();

        $user = User::query()->find($userId);
        if (!$user) {
            MessageService::abort(404, 'messages.user.not_found');
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        $subscriptions = $this->subscriptionService->index([
            'user_id' => $userId,
            'per_page' => $perPage,
            'sort_field' => 'created_at',
            'sort_order' => 'desc',
        ]);

        $payments = $this->paymentService->index([
            'user_id' => $userId,
            'per_page' => $perPage,
            'sort_field' => 'created_at',
            'sort_order' => 'desc',
        ]);

        $invoices = $this->invoiceService->index([
            'user_id' => $userId,
            'per_page' => $perPage,
            'sort_field' => 'issued_at',
            'sort_order' => 'desc',
        ]);

        $events = SubscriptionEvent::query()
            ->with(['plan', 'subscription'])
            ->whereHas('subscription', fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return [
            'user_id' => $userId,
            'subscriptions' => $subscriptions,
            'payments' => $payments,
            'invoices' => $invoices,
            'events' => $events,
        ];
    }
}
