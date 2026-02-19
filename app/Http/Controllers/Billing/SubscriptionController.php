<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\PurchasePlanRequest;
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
}

