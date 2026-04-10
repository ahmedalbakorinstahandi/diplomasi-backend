<?php

namespace App\Http\Services\Billing;

use App\Exceptions\Billing\OwnershipConflictException;
use App\Models\Billing\AppleIapSubscriptionOwnership;
use App\Models\Billing\Plan;
use App\Models\Billing\PaymentTransaction;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionEvent;
use App\Support\Billing\AppleIapTransactionFingerprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AppleIapService
{
    private const PRODUCTION_URL = 'https://buy.itunes.apple.com/verifyReceipt';
    private const SANDBOX_URL = 'https://sandbox.itunes.apple.com/verifyReceipt';
    private const STATUS_OK = 0;
    private const STATUS_SANDBOX_RECEIPT_ON_PRODUCTION = 21007;

    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Apple verify succeeded: assert lineage ownership, then activate subscription (or preflight only).
     *
     * @return array{preflight: true, outcome: string}|array{preflight: false, subscription: Subscription}
     */
    public function processVerifiedAppleReceipt(
        int $userId,
        int $planId,
        string $expectedProductId,
        array $verifyResponse,
        array $latestTransaction,
        bool $preflight
    ): array {
        $otid = (string) ($latestTransaction['original_transaction_id'] ?? '');
        if ($otid === '') {
            throw new \RuntimeException('Missing original_transaction_id');
        }

        $env = isset($verifyResponse['environment']) ? (string) $verifyResponse['environment'] : null;

        return DB::transaction(function () use ($userId, $planId, $expectedProductId, $verifyResponse, $latestTransaction, $preflight, $otid, $env) {
            $outcome = $this->claimOrAssertAppleOwnership(
                $userId,
                $otid,
                $planId,
                $expectedProductId,
                $env,
                $preflight
            );

            if ($preflight) {
                return [
                    'preflight' => true,
                    'outcome' => $outcome,
                ];
            }

            $subscription = $this->handleVerifiedReceipt($userId, $verifyResponse, $latestTransaction);

            return [
                'preflight' => false,
                'subscription' => $subscription,
            ];
        });
    }

    /**
     * Enforces: one Apple `original_transaction_id` → one internal user (no transfer).
     * Uses row lock + UNIQUE with deterministic duplicate-key retry.
     *
     * @return 'unclaimed'|'owned_by_current_user'
     *
     * @throws OwnershipConflictException
     */
    private function claimOrAssertAppleOwnership(
        int $userId,
        string $originalTransactionId,
        int $planId,
        string $productId,
        ?string $environment,
        bool $preflight
    ): string {
        $existing = AppleIapSubscriptionOwnership::query()
            ->where('original_transaction_id', $originalTransactionId)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            if ((int) $existing->user_id !== $userId) {
                throw new OwnershipConflictException((int) $existing->user_id);
            }

            return 'owned_by_current_user';
        }

        if ($preflight) {
            return 'unclaimed';
        }

        try {
            AppleIapSubscriptionOwnership::query()->create([
                'user_id' => $userId,
                'original_transaction_id' => $originalTransactionId,
                'plan_id' => $planId,
                'product_id' => $productId,
                'environment' => $environment,
                'linked_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $existing = AppleIapSubscriptionOwnership::query()
                ->where('original_transaction_id', $originalTransactionId)
                ->first();
            if (!$existing) {
                throw $e;
            }
            if ((int) $existing->user_id !== $userId) {
                throw new OwnershipConflictException((int) $existing->user_id);
            }
        }

        return 'unclaimed';
    }

    /**
     * Log helper: never log raw receipts; use fingerprint for original_transaction_id.
     */
    public static function fingerprintOriginalTransactionId(string $originalTransactionId): array
    {
        return AppleIapTransactionFingerprint::forLog($originalTransactionId);
    }

    /**
     * التحقق من إيصال Apple. عند status 21007 إعادة الطلب إلى Sandbox.
     *
     * @return array{status: int, latest_receipt_info?: array, pending_renewal_info?: array, environment?: string}
     */
    public function verifyReceipt(string $receiptData, string $productId, ?string $transactionId = null): array
    {
        $normalizedReceipt = $this->normalizeReceiptData($receiptData);

        if ($this->looksLikeStoreKitJws($normalizedReceipt)) {
            // StoreKit 2/TestFlight may send signed transaction JWS instead of legacy app receipt.
            // We normalize it into verifyReceipt-like shape so existing billing flow can proceed.
            return $this->parseStoreKitJwsAsVerifyResponse($normalizedReceipt);
        }

        $secret = (string) config('services.apple.shared_secret');
        if ($secret === '') {
            throw new \InvalidArgumentException('Apple IAP shared secret is not configured');
        }

        $body = [
            'receipt-data' => $normalizedReceipt,
            'password' => $secret,
            'exclude-old-transactions' => true,
        ];

        $response = $this->sendVerifyRequest(self::PRODUCTION_URL, $body);

        if (isset($response['status']) && (int) $response['status'] === self::STATUS_SANDBOX_RECEIPT_ON_PRODUCTION) {
            Log::channel('single')->info('[apple.iap] Retrying verifyReceipt on sandbox (21007)');
            $response = $this->sendVerifyRequest(self::SANDBOX_URL, $body);
        }

        $status = (int) ($response['status'] ?? -1);
        if ($status !== self::STATUS_OK) {
            Log::channel('single')->warning('[apple.iap] verifyReceipt failed', ['status' => $status, 'response' => $response]);
            throw new \RuntimeException('Apple receipt verification failed', $status);
        }

        return $response;
    }

    /**
     * Lightweight receipt inspection for diagnostics/logging.
     * Does not verify signature and never returns full receipt body.
     */
    public function inspectReceipt(string $receiptData): array
    {
        $normalized = $this->normalizeReceiptData($receiptData);
        $isJws = $this->looksLikeStoreKitJws($normalized);
        $payload = $isJws ? ($this->decodeStoreKitJwsPayload($normalized) ?? []) : [];

        return [
            'receipt_length' => strlen($normalized),
            'dot_segments' => substr_count($normalized, '.'),
            'is_jws' => $isJws,
            'jws_environment' => $isJws ? ($payload['environment'] ?? null) : null,
            'jws_product_id' => $isJws ? ($payload['productId'] ?? null) : null,
            'jws_transaction_id' => $isJws ? ($payload['transactionId'] ?? null) : null,
            'jws_original_transaction_id' => $isJws ? ($payload['originalTransactionId'] ?? null) : null,
        ];
    }

    /**
     * استخراج أحدث معاملة للـ product_id (أو transaction_id إن وُجد) من رد Apple.
     *
     * @return array{original_transaction_id: string, transaction_id: string, product_id: string, expires_date_ms: ?string, purchase_date_ms: string, ...}
     */
    public function extractLatestTransaction(array $verifyResponse, string $productId, ?string $transactionId = null): array
    {
        $latest = $verifyResponse['latest_receipt_info'] ?? [];
        if (!is_array($latest) || empty($latest)) {
            throw new \RuntimeException('No latest_receipt_info in Apple response');
        }

        $candidates = array_filter($latest, function ($t) use ($productId, $transactionId) {
            $pid = (string) ($t['product_id'] ?? '');
            if ($pid !== $productId) {
                return false;
            }
            if ($transactionId !== null && $transactionId !== '') {
                return ((string) ($t['transaction_id'] ?? '')) === $transactionId;
            }
            return true;
        });

        if (empty($candidates)) {
            throw new \RuntimeException('No matching transaction for product_id in receipt');
        }

        // أحدث معاملة (أعلى expires_date_ms أو purchase_date_ms)
        usort($candidates, function ($a, $b) {
            $expA = (string) ($a['expires_date_ms'] ?? $a['expires_date'] ?? $a['purchase_date_ms'] ?? '0');
            $expB = (string) ($b['expires_date_ms'] ?? $b['expires_date'] ?? $b['purchase_date_ms'] ?? '0');
            return strcmp($expB, $expA);
        });
        $tx = $candidates[0];

        $expiresMs = $tx['expires_date_ms'] ?? $tx['expires_date'] ?? null;
        $purchaseMs = (string) ($tx['purchase_date_ms'] ?? $tx['purchase_date'] ?? '0');

        return [
            'original_transaction_id' => (string) ($tx['original_transaction_id'] ?? ''),
            'transaction_id' => (string) ($tx['transaction_id'] ?? ''),
            'product_id' => (string) ($tx['product_id'] ?? ''),
            'expires_date_ms' => $expiresMs !== null ? (string) $expiresMs : null,
            'purchase_date_ms' => $purchaseMs,
        ];
    }

    /**
     * استخراج auto_renew_status من pending_renewal_info لـ original_transaction_id.
     */
    public function getAutoRenewStatus(array $verifyResponse, string $originalTransactionId): bool
    {
        $pending = $verifyResponse['pending_renewal_info'] ?? [];
        if (!is_array($pending)) {
            return true;
        }
        foreach ($pending as $p) {
            if (((string) ($p['original_transaction_id'] ?? '')) === $originalTransactionId) {
                $status = (string) ($p['auto_renew_status'] ?? '1');
                return $status === '1';
            }
        }
        return true;
    }

    /**
     * إنشاء أو تحديث الاشتراك وتسجيل الحدث بعد التحقق من الإيصال.
     *
     * @param array $latestTransaction خرج extractLatestTransaction
     */
    public function handleVerifiedReceipt(int $userId, array $verifyResponse, array $latestTransaction): Subscription
    {
        $productId = $latestTransaction['product_id'];
        $plan = Plan::query()->where('ios_product_id', $productId)->first();
        if (!$plan) {
            throw new \RuntimeException("No plan linked to Apple product_id: {$productId}");
        }

        $expiresMs = $latestTransaction['expires_date_ms'];
        $purchaseMs = $latestTransaction['purchase_date_ms'];
        $originalTransactionId = $latestTransaction['original_transaction_id'];
        $transactionId = $latestTransaction['transaction_id'];

        $startDate = $this->msToCarbon($purchaseMs);
        $endDate = $expiresMs !== null ? $this->msToCarbon($expiresMs) : $this->computeNextEndDate($startDate->copy(), (string) $plan->interval);

        $price = $plan->ios_price ?? $plan->price;
        $currency = strtoupper((string) ($plan->ios_currency ?? config('services.moyasar.currency', 'SAR')));
        $autoRenewBase = $this->getAutoRenewStatus($verifyResponse, $originalTransactionId);

        $now = now();
        $isExpired = $endDate->lt($now);
        $status = $isExpired ? 'expired' : 'active';
        $autoRenew = $isExpired ? false : $autoRenewBase;

        $subscription = Subscription::query()
            ->where('user_id', $userId)
            ->where('plan_id', $plan->id)
            ->orderByDesc('id')
            ->first();

        $isNew = false;
        if ($subscription) {
            $subscription->update([
                'provider' => 'apple',
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'price' => $price,
                'currency' => $currency,
                'auto_renew' => $autoRenew,
                'cancel_at_period_end' => false,
                'canceled_at' => null,
            ]);
            $subscription = $subscription->fresh();
        } else {
            $isNew = true;
            $subscription = Subscription::query()->create([
                'user_id' => $userId,
                'plan_id' => $plan->id,
                'provider' => 'apple',
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'price' => $price,
                'currency' => $currency,
                'auto_renew' => $autoRenew,
                'cancel_at_period_end' => false,
                'canceled_at' => null,
            ]);
        }

        $eventType = $isNew ? 'created' : 'renewed';
        $amountCharged = $price ? (float) $price : 0;

        SubscriptionEvent::query()->create([
            'subscription_id' => $subscription->id,
            'event_type' => $eventType,
            'plan_id' => (int) $plan->id,
            'status' => $status,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'plan_price' => $price,
            'amount_charged' => $amountCharged,
            'amount_refunded' => 0,
            'currency' => $currency,
            'meta' => [
                'apple_original_transaction_id' => $originalTransactionId,
                'apple_transaction_id' => $transactionId,
            ],
        ]);

        return $subscription;
    }

    /**
     * إنشاء معاملة دفع وفاتورة لشراء Apple ثم ربطها بالاشتراك.
     */
    public function createPaymentTransaction(
        int $userId,
        int $planId,
        int $subscriptionId,
        array $latestTransaction,
        array $verifyResponse
    ): PaymentTransaction {
        $plan = Plan::query()->find($planId);
        if (!$plan) {
            throw new \InvalidArgumentException('Plan not found');
        }

        $price = $plan->ios_price ?? $plan->price;
        $currency = strtoupper((string) ($plan->ios_currency ?? config('services.moyasar.currency', 'SAR')));
        $amountMinor = $this->toMinorUnits((string) $price, $currency);

        $transactionId = $latestTransaction['transaction_id'];
        $originalTransactionId = $latestTransaction['original_transaction_id'];

        $existing = PaymentTransaction::query()
            ->where('provider', 'apple')
            ->where('provider_payment_id', $transactionId)
            ->first();
        if ($existing) {
            return $existing;
        }

        $merchantReferenceId = (string) Str::uuid();
        $givenId = (string) Str::uuid();

        $transaction = DB::transaction(function () use (
            $userId,
            $planId,
            $subscriptionId,
            $merchantReferenceId,
            $givenId,
            $transactionId,
            $originalTransactionId,
            $amountMinor,
            $currency,
            $latestTransaction,
            $verifyResponse
        ) {
            $t = PaymentTransaction::query()->create([
                'user_id' => $userId,
                'plan_id' => $planId,
                'subscription_id' => $subscriptionId,
                'merchant_reference_id' => $merchantReferenceId,
                'given_id' => $givenId,
                'provider' => 'apple',
                'provider_payment_id' => $transactionId,
                'original_transaction_id' => $originalTransactionId,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'attempt_no' => 1,
                'status' => 'paid',
                'gateway_status' => 'verified',
                'finalized_at' => now(),
                'verified_at' => now(),
                'raw_response' => [
                    'latest_transaction' => $latestTransaction,
                    'environment' => $verifyResponse['environment'] ?? null,
                ],
            ]);

            $this->invoiceService->issueFromTransaction($t);

            $event = SubscriptionEvent::query()
                ->where('subscription_id', $subscriptionId)
                ->where('meta->apple_transaction_id', $transactionId)
                ->latest('id')
                ->first();
            if ($event) {
                $meta = is_array($event->meta) ? $event->meta : [];
                $meta['payment_transaction_id'] = $t->id;
                $event->update(['meta' => $meta]);
            }

            return $t->fresh();
        });

        return $transaction;
    }


    private function parseStoreKitJwsAsVerifyResponse(string $receiptData): array
    {
        $json = $this->decodeStoreKitJwsPayload($receiptData);
        if (!is_array($json)) {
            throw new \RuntimeException('Invalid StoreKit JWS payload');
        }

        $tx = [
            'original_transaction_id' => (string) ($json['originalTransactionId'] ?? $json['transactionId'] ?? ''),
            'transaction_id' => (string) ($json['transactionId'] ?? ''),
            'product_id' => (string) ($json['productId'] ?? ''),
            'expires_date_ms' => isset($json['expiresDate']) ? (string) $json['expiresDate'] : null,
            'purchase_date_ms' => isset($json['purchaseDate']) ? (string) $json['purchaseDate'] : '0',
        ];

        return [
            'status' => self::STATUS_OK,
            'environment' => 'Xcode',
            'latest_receipt_info' => [$tx],
            'pending_renewal_info' => [[
                'original_transaction_id' => $tx['original_transaction_id'],
                'auto_renew_status' => '1',
            ]],
        ];
    }
    private function looksLikeStoreKitJws(string $receiptData): bool
    {
        $normalized = $this->normalizeReceiptData($receiptData);
        if (substr_count($normalized, '.') !== 2) {
            return false;
        }

        $parts = explode('.', $normalized);
        if (count($parts) !== 3) {
            return false;
        }

        $json = $this->decodeStoreKitJwsPayload($normalized);
        if (!is_array($json)) {
            return false;
        }

        // Signed transaction payloads include these keys in StoreKit 2 JWS.
        return isset($json['productId']) || isset($json['transactionId']) || isset($json['originalTransactionId']);
    }

    private function normalizeReceiptData(string $receiptData): string
    {
        $value = trim($receiptData);

        // If the client sends a JSON-encoded string, unwrap it.
        if ($value !== '' && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $decodedString = json_decode($value, true);
            if (is_string($decodedString) && $decodedString !== '') {
                $value = $decodedString;
            }
        }

        // Remove accidental escapes/newlines/spaces that break JWS/base64 parsing.
        $value = str_replace(["\r", "\n", "\t"], '', $value);
        $value = str_replace('\/', '/', $value);
        $value = trim($value);

        return $value;
    }

    private function decodeStoreKitJwsPayload(string $jws): ?array
    {
        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            return null;
        }

        $decoded = $this->base64UrlDecode($parts[1]);
        if ($decoded === null) {
            return null;
        }

        $json = json_decode($decoded, true);
        return is_array($json) ? $json : null;
    }

    private function base64UrlDecode(string $value): ?string
    {
        $base64 = strtr($value, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($base64, true);
        return $decoded === false ? null : $decoded;
    }

    private function sendVerifyRequest(string $url, array $body): array
    {
        $response = Http::timeout(15)->post($url, $body);
        /** @var array|null $data */
        $data = $response->json();
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid response from Apple verifyReceipt');
        }
        return $data;
    }

    private function msToCarbon(string $ms): Carbon
    {
        $sec = (int) round(((float) $ms) / 1000);

        return Carbon::createFromTimestamp($sec);
    }

    private function computeNextEndDate(Carbon $periodStart, string $interval): Carbon
    {
        $interval = strtolower(trim($interval));
        if (preg_match('/^(\d+)_months?$/', $interval, $m)) {
            $months = (int) $m[1];
            return $periodStart->copy()->addMonths($months >= 1 && $months <= 60 ? $months : 1);
        }
        return match ($interval) {
            'annual', 'yearly' => $periodStart->copy()->addYear(),
            'semi_annual' => $periodStart->copy()->addMonths(6),
            'quarterly' => $periodStart->copy()->addMonths(3),
            '4_months', 'four_months' => $periodStart->copy()->addMonths(4),
            'monthly' => $periodStart->copy()->addMonth(),
            default => $periodStart->copy()->addMonth(),
        };
    }

    private function toMinorUnits(string $amount, string $currency): int
    {
        $amount = preg_replace('/\s+/', '', $amount);
        $value = (float) str_replace(',', '.', $amount);
        $minor = (int) round($value * 100);
        return max(0, $minor);
    }
}
