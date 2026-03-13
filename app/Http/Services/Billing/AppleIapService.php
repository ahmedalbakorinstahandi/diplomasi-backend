<?php

namespace App\Http\Services\Billing;

use App\Models\Billing\Plan;
use App\Models\Billing\PaymentTransaction;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionEvent;
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
     * التحقق من إيصال Apple. عند status 21007 إعادة الطلب إلى Sandbox.
     *
     * @return array{status: int, latest_receipt_info?: array, pending_renewal_info?: array, environment?: string}
     */
    public function verifyReceipt(string $receiptData, string $productId, ?string $transactionId = null): array
    {
        $secret = (string) config('services.apple.shared_secret');
        if ($secret === '') {
            throw new \InvalidArgumentException('Apple IAP shared secret is not configured');
        }

        $body = [
            'receipt-data' => $receiptData,
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
        $autoRenew = $this->getAutoRenewStatus($verifyResponse, $originalTransactionId);

        $subscription = Subscription::query()
            ->where('user_id', $userId)
            ->where('plan_id', $plan->id)
            ->orderByDesc('id')
            ->first();

        $isNew = false;
        if ($subscription) {
            $subscription->update([
                'provider' => 'apple',
                'status' => 'active',
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
                'status' => 'active',
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
            'status' => 'active',
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
