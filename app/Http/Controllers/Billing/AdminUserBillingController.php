<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\InvoiceResource;
use App\Http\Resources\Billing\PaymentTransactionResource;
use App\Http\Resources\Billing\SubscriptionEventResource;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Http\Services\Billing\AdminUserBillingService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class AdminUserBillingController extends Controller
{
    public function __construct(
        protected AdminUserBillingService $billingService
    ) {}

    public function billing(Request $request, int $id)
    {
        $summary = $this->billingService->billingSummary($id, $request->all());

        return ResponseService::response([
            'success' => true,
            'data' => [
                'user_id' => $summary['user_id'],
                'subscriptions' => SubscriptionResource::collection($summary['subscriptions']->items())->resolve(),
                'subscriptions_meta' => [
                    'current_page' => $summary['subscriptions']->currentPage(),
                    'last_page' => $summary['subscriptions']->lastPage(),
                    'per_page' => $summary['subscriptions']->perPage(),
                    'total' => $summary['subscriptions']->total(),
                ],
                'payments' => PaymentTransactionResource::collection($summary['payments']->items())->resolve(),
                'payments_meta' => [
                    'current_page' => $summary['payments']->currentPage(),
                    'last_page' => $summary['payments']->lastPage(),
                    'per_page' => $summary['payments']->perPage(),
                    'total' => $summary['payments']->total(),
                ],
                'invoices' => InvoiceResource::collection($summary['invoices']->items())->resolve(),
                'invoices_meta' => [
                    'current_page' => $summary['invoices']->currentPage(),
                    'last_page' => $summary['invoices']->lastPage(),
                    'per_page' => $summary['invoices']->perPage(),
                    'total' => $summary['invoices']->total(),
                ],
                'events' => SubscriptionEventResource::collection($summary['events']->items())->resolve(),
                'events_meta' => [
                    'current_page' => $summary['events']->currentPage(),
                    'last_page' => $summary['events']->lastPage(),
                    'per_page' => $summary['events']->perPage(),
                    'total' => $summary['events']->total(),
                ],
            ],
            'status' => 200,
        ]);
    }
}
