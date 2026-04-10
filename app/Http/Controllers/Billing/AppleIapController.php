<?php

namespace App\Http\Controllers\Billing;

use App\Exceptions\Billing\OwnershipConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\VerifyApplePurchaseRequest;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Http\Services\Billing\AppleIapService;
use App\Models\Billing\Plan;
use App\Models\Users\User;
use App\Services\MessageService;
use App\Services\ResponseService;
use App\Support\Billing\AppleIapTransactionFingerprint;
use App\Support\Billing\MaskEmailHint;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AppleIapController extends Controller
{
    public function __construct(
        protected AppleIapService $appleIapService
    ) {}

    /**
     * Verify iOS purchase, activate subscription, and create invoice.
     * Optional preflight=true: Apple verify + ownership check only (no writes except optional audit).
     */
    public function verify(VerifyApplePurchaseRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $validated = $request->validated();

        $planId = (int) $validated['plan_id'];
        $providedProductId = (string) $validated['product_id'];
        $transactionId = isset($validated['transaction_id'])
            ? (string) $validated['transaction_id']
            : null;
        $receipt = (string) $validated['receipt'];
        $preflight = filter_var($validated['preflight'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $plan = Plan::query()->find($planId);
        if (!$plan) {
            MessageService::response([
                'success' => false,
                'message' => "Plan not found for plan_id {$planId}.",
                'key' => 'billing.ios.plan_not_found',
                'info' => [
                    'plan_id' => $planId,
                    'provided_product_id' => $providedProductId,
                ],
            ], 422);
        }

        $expectedProductId = (string) ($plan->ios_product_id ?? '');
        if ($expectedProductId === '') {
            MessageService::response([
                'success' => false,
                'message' => "Selected plan_id {$planId} is not mapped to an iOS product.",
                'key' => 'billing.ios.plan_product_missing',
                'info' => [
                    'plan_id' => $planId,
                    'provided_product_id' => $providedProductId,
                    'hint' => 'Set plans.ios_product_id for this plan in the database.',
                ],
            ], 422);
        }

        $productId = $expectedProductId;
        $receiptInfo = $this->appleIapService->inspectReceipt($receipt);

        Log::channel('single')->info('[apple.iap.verify] incoming', [
            'user_id' => $userId,
            'plan_id' => $planId,
            'preflight' => $preflight,
            'provided_product_id' => $providedProductId,
            'expected_product_id' => $expectedProductId,
            'transaction_id' => $transactionId,
            'receipt' => $receiptInfo,
        ]);

        if ($providedProductId !== $expectedProductId) {
            Log::channel('single')->warning('[apple.iap.verify] provided product mismatch (using expected from DB)', [
                'user_id' => $userId,
                'plan_id' => $planId,
                'provided_product_id' => $providedProductId,
                'expected_product_id' => $expectedProductId,
            ]);
        }

        try {
            $verifyResponse = $this->appleIapService->verifyReceipt($receipt, $productId, $transactionId);
            $latestTransaction = $this->appleIapService->extractLatestTransaction($verifyResponse, $productId, $transactionId);
        } catch (\Throwable $e) {
            report($e);

            $errorMessage = 'Apple receipt verification failed.';
            $errorKey = 'billing.ios.verify_failed';
            $hint = 'Check APPLE_IAP_SHARED_SECRET, product setup in App Store Connect, and test via Sandbox/TestFlight.';
            $info = [
                'plan_id' => $planId,
                'provided_product_id' => $providedProductId,
                'expected_product_id' => $expectedProductId,
                'transaction_id' => $transactionId,
                'apple_status' => (int) $e->getCode(),
                'reason' => $e->getMessage(),
                'hint' => $hint,
                'receipt' => $receiptInfo,
            ];

            if ((int) $e->getCode() === 21002) {
                $diagnostics = $this->buildReceiptDiagnostics($receipt);
                $errorMessage = 'Apple receipt is invalid or in unsupported format (status 21002).';
                $errorKey = 'billing.ios.verify_failed.invalid_receipt';
                $hint = 'Send a valid App Store receipt/JWS. Check payload integrity and make sure receipt data is passed exactly as received from iOS.';
                $info['hint'] = $hint;
                $info['receipt_diagnostics'] = $diagnostics;
            }

            if (str_contains(strtolower((string) $e->getMessage()), 'storekit local jws receipt')) {
                $errorMessage = 'Xcode StoreKit local JWS receipt is not supported by legacy verifyReceipt endpoint.';
                $errorKey = 'billing.ios.verify_failed.storekit_local';
                $hint = 'Use Sandbox/TestFlight for real verification, or enable APPLE_IAP_ALLOW_XCODE_LOCAL=true for local dev only.';
                $info['hint'] = $hint;
            }

            MessageService::response([
                'success' => false,
                'message' => $errorMessage,
                'key' => $errorKey,
                'info' => $info,
            ], 422);
        }

        $otid = (string) ($latestTransaction['original_transaction_id'] ?? '');
        if ($otid === '') {
            MessageService::response([
                'success' => false,
                'message' => 'Receipt did not contain original_transaction_id.',
                'key' => 'billing.ios.verify_failed.missing_original_transaction_id',
                'info' => ['plan_id' => $planId],
            ], 422);
        }

        try {
            $payload = $this->appleIapService->processVerifiedAppleReceipt(
                $userId,
                $planId,
                $expectedProductId,
                $verifyResponse,
                $latestTransaction,
                $preflight
            );
        } catch (OwnershipConflictException $e) {
            $this->logOwnershipConflict($userId, $otid, $e->ownerUserId, $preflight);

            $owner = User::query()->find($e->ownerUserId);
            $masked = MaskEmailHint::format($owner?->email);

            MessageService::response([
                'success' => false,
                'message' => trans('billing.ios.already_linked_to_another_account'),
                'key' => 'billing.ios.already_linked_to_another_account',
                'info' => [
                    'masked_owner_email' => $masked,
                ],
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            MessageService::response([
                'success' => false,
                'message' => 'Receipt was verified, but subscription activation or billing creation failed.',
                'key' => 'billing.ios.activate_failed',
                'info' => [
                    'plan_id' => $planId,
                    'provided_product_id' => $providedProductId,
                    'expected_product_id' => $expectedProductId,
                    'transaction_id' => $transactionId,
                    'reason' => $e->getMessage(),
                    'hint' => 'Check subscription writes and plan mapping integrity.',
                ],
            ], 422);
        }

        if ($preflight) {
            return ResponseService::response([
                'success' => true,
                'key' => 'billing.ios.preflight_ok',
                'data' => [
                    'eligible' => true,
                    'outcome' => $payload['outcome'],
                ],
                'status' => 200,
            ]);
        }

        /** @var \App\Models\Billing\Subscription $subscription */
        $subscription = $payload['subscription'];
        $subscription = $subscription->fresh(['plan']);

        Log::channel('single')->info('[apple.iap.verify] activation ok', [
            'user_id' => $userId,
            'plan_id' => $planId,
            'expected_product_id' => $expectedProductId,
            'latest_transaction_product_id' => $latestTransaction['product_id'] ?? null,
            'latest_transaction_id' => $latestTransaction['transaction_id'] ?? null,
            'original_transaction_id' => AppleIapTransactionFingerprint::forLog($otid),
        ]);

        $billingWarning = null;
        try {
            $this->appleIapService->createPaymentTransaction(
                $userId,
                $planId,
                (int) $subscription->id,
                $latestTransaction,
                $verifyResponse
            );
        } catch (\Throwable $e) {
            report($e);
            $billingWarning = [
                'key' => 'billing.ios.artifacts_failed',
                'message' => 'Subscription is active, but invoice/payment artifacts could not be created right now.',
                'reason' => $e->getMessage(),
            ];
        }

        $response = [
            'success' => true,
            'data' => $subscription,
            'resource' => SubscriptionResource::class,
            'status' => 200,
        ];
        if ($billingWarning !== null) {
            $response['info'] = ['billing_warning' => $billingWarning];
        }

        return ResponseService::response($response);
    }

    private function logOwnershipConflict(int $requestingUserId, string $otid, int $ownerUserId, bool $preflight): void
    {
        Log::channel('single')->warning('[apple.iap.verify] ownership conflict', [
            'requesting_user_id' => $requestingUserId,
            'owner_user_id' => $ownerUserId,
            'preflight' => $preflight,
            'original_transaction_id' => AppleIapTransactionFingerprint::forLog($otid),
        ]);
    }

    /**
     * Build safe diagnostics for malformed/unsupported receipts (21002).
     */
    private function buildReceiptDiagnostics(string $receipt): array
    {
        $trimmed = trim($receipt);
        $segmentCount = substr_count($trimmed, '.');
        $isLikelyJws = $segmentCount === 2
            && preg_match('/^[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$/', $trimmed) === 1;

        $base64Decoded = base64_decode($trimmed, true);
        $base64Valid = $base64Decoded !== false;

        return [
            'receipt_length' => strlen($trimmed),
            'receipt_format' => $isLikelyJws ? 'jws' : ($base64Valid ? 'base64' : 'unknown'),
            'jws_segments' => $segmentCount,
            'base64_valid' => $base64Valid,
            'contains_whitespace' => preg_match('/\s/', $receipt) === 1,
            'contains_space_char' => str_contains($receipt, ' '),
            'contains_plus_char' => str_contains($receipt, '+'),
            'contains_percent2b' => str_contains($receipt, '%2B') || str_contains($receipt, '%2b'),
        ];
    }
}
