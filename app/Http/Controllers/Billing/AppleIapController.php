<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\VerifyApplePurchaseRequest;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Http\Services\Billing\AppleIapService;
use App\Models\Billing\Plan;
use App\Services\MessageService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;

class AppleIapController extends Controller
{
    public function __construct(
        protected AppleIapService $appleIapService
    ) {}

    /**
     * التحقق من شراء iOS وإصدار الاشتراك والفاتورة.
     */
    public function verify(VerifyApplePurchaseRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $validated = $request->validated();
        $planId = (int) $validated['plan_id'];
        $productId = (string) $validated['product_id'];
        $transactionId = (string) $validated['transaction_id'];
        $receipt = (string) $validated['receipt'];

        $plan = Plan::query()->find($planId);
        if (!$plan || (string) $plan->ios_product_id !== $productId) {
            MessageService::response([
                'success' => false,
                'message' => 'الخطة لا تطابق منتج Apple المحدد.',
                'key' => 'billing.ios.plan_product_mismatch',
            ], 422);
        }

        try {
            $verifyResponse = $this->appleIapService->verifyReceipt($receipt, $productId, $transactionId);
            $latestTransaction = $this->appleIapService->extractLatestTransaction($verifyResponse, $productId, $transactionId);
        } catch (\Throwable $e) {
            report($e);
            MessageService::response([
                'success' => false,
                'message' => 'فشل التحقق من الإيصال مع Apple.',
                'key' => 'billing.ios.verify_failed',
            ], 422);
        }

        try {
            $subscription = $this->appleIapService->handleVerifiedReceipt($userId, $verifyResponse, $latestTransaction);
            $this->appleIapService->createPaymentTransaction(
                $userId,
                $planId,
                (int) $subscription->id,
                $latestTransaction,
                $verifyResponse
            );
            $subscription = $subscription->fresh(['plan']);
        } catch (\Throwable $e) {
            report($e);
            MessageService::response([
                'success' => false,
                'message' => 'فشل تفعيل الاشتراك.',
                'key' => 'billing.ios.activate_failed',
            ], 422);
        }

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'resource' => SubscriptionResource::class,
            'status' => 200,
        ]);
    }
}
