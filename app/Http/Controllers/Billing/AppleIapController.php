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
     * Verify iOS purchase, activate subscription, and create invoice.
     */
    public function verify(VerifyApplePurchaseRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $validated = $request->validated();

        $planId = (int) $validated['plan_id'];
        $productId = (string) $validated['product_id'];
        $transactionId = isset($validated['transaction_id'])
            ? (string) $validated['transaction_id']
            : null;
        $receipt = (string) $validated['receipt'];

        $plan = Plan::query()->find($planId);
        $expectedProductId = $plan ? (string) ($plan->ios_product_id ?? '') : null;

        // 1) Ensure plan_id is mapped to the same Apple product_id.
        if (!$plan || $expectedProductId !== $productId) {
            MessageService::response([
                'success' => false,
                'message' => 'Plan/Product mismatch: plan_id {$planId} is not mapped to the provided Apple product_id {$productId}.',
                'key' => 'billing.ios.plan_product_mismatch',
                'info' => [
                    'plan_id' => $planId,
                    'provided_product_id' => $productId,
                    'expected_product_id' => $expectedProductId,
                    'hint' => 'Update plans.ios_product_id in the database or send the correct product from App Store Connect.',
                ],
            ], 422);
        }

        // 2) Verify receipt with Apple.
        try {
            $verifyResponse = $this->appleIapService->verifyReceipt($receipt, $productId, $transactionId);
            $latestTransaction = $this->appleIapService->extractLatestTransaction($verifyResponse, $productId, $transactionId);
        } catch (\Throwable $e) {
            report($e);

            $errorMessage = 'Apple receipt verification failed.';
            $errorKey = 'billing.ios.verify_failed';
            $hint = 'Check APPLE_IAP_SHARED_SECRET, product setup in App Store Connect, and test via Sandbox/TestFlight.';

            if (str_contains(strtolower((string) $e->getMessage()), 'storekit local jws receipt')) {
                $errorMessage = 'Xcode StoreKit local JWS receipt is not supported by legacy verifyReceipt endpoint.';
                $errorKey = 'billing.ios.verify_failed.storekit_local';
                $hint = 'Use Sandbox/TestFlight for real verification, or enable APPLE_IAP_ALLOW_XCODE_LOCAL=true for local dev only.';
            }

            MessageService::response([
                'success' => false,
                'message' => $errorMessage,
                'key' => $errorKey,
                'info' => [
                    'plan_id' => $planId,
                    'product_id' => $productId,
                    'transaction_id' => $transactionId,
                    'reason' => $e->getMessage(),
                    'hint' => $hint,
                ],
            ], 422);
        }

        // 3) Activate subscription and issue billing artifacts.
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
                'message' => 'Receipt was verified, but subscription activation or billing creation failed.',
                'key' => 'billing.ios.activate_failed',
                'info' => [
                    'plan_id' => $planId,
                    'product_id' => $productId,
                    'transaction_id' => $transactionId,
                    'reason' => $e->getMessage(),
                    'hint' => 'Check subscriptions/payment_transactions writes and plan mapping integrity.',
                ],
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