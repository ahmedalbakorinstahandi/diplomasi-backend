<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\PurchasePlanRequest;
use App\Http\Requests\Billing\PurchasePlanWithPaymentRequest;
use App\Http\Services\Billing\MoyasarPaymentService;
use App\Http\Services\Billing\SubscriptionManagementService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionManagementService $subscriptionManagementService,
        protected MoyasarPaymentService $moyasarPaymentService
    ) {}

    public function current(Request $request)
    {
        $subscription = $this->subscriptionManagementService->currentForUser((int) $request->user()->id);

        return ResponseService::response([
            'success' => true,
            'status' => 200,
            'data' => $subscription,
        ]);
    }

    public function cancelAtPeriodEnd(Request $request)
    {
        $subscription = $this->subscriptionManagementService->cancelAtPeriodEnd((int) $request->user()->id);

        return ResponseService::response([
            'success' => true,
            'status' => 200,
            'message' => 'Subscription auto-renew disabled. It will end at period end.',
            'data' => $subscription,
        ]);
    }

    public function resumeAutoRenew(Request $request)
    {
        $subscription = $this->subscriptionManagementService->resumeAutoRenew((int) $request->user()->id);

        return ResponseService::response([
            'success' => true,
            'status' => 200,
            'message' => 'Subscription auto-renew enabled.',
            'data' => $subscription,
        ]);
    }

    public function retryPayment(Request $request)
    {
        $result = $this->moyasarPaymentService->retryCurrentSubscription((int) $request->user()->id);

        return ResponseService::response([
            'success' => true,
            'status' => 200,
            'data' => $result,
        ]);
    }

    public function purchasePlan(PurchasePlanRequest $request)
    {
        $result = $this->moyasarPaymentService->purchasePlanForUser(
            userId: (int) $request->user()->id,
            planId: (int) $request->validated('plan_id')
        );

        return ResponseService::response([
            'success' => true,
            'status' => 200,
            'data' => $result,
        ]);
    }

    public function purchasePlanWithPayment(PurchasePlanWithPaymentRequest $request)
    {
        $validated = $request->validated();

        $result = $this->moyasarPaymentService->purchasePlanWithGatewayPayment(
            (int) $request->user()->id,
            (int) $validated['plan_id'],
            (string) $validated['gateway_payment_id'],
            [
                'token' => $validated['token'] ?? null,
                'brand' => $validated['brand'] ?? null,
                'last4' => $validated['last4'] ?? null,
                'exp_month' => $validated['exp_month'] ?? null,
                'exp_year' => $validated['exp_year'] ?? null,
                'meta' => $validated['meta'] ?? null,
                'is_default' => true,
                'status' => 'active',
                'refund_verification' => false,
            ]
        );

        return ResponseService::response([
            'success' => true,
            'status' => 200,
            'data' => $result,
        ]);
    }
}

