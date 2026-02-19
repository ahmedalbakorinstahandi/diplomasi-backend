<?php

namespace App\Http\Services\Billing;

use App\Models\Billing\PaymentTransaction;
use App\Models\Billing\Plan;
use App\Models\Billing\SavedPaymentMethod;
use App\Models\Billing\Subscription;
use App\Services\MessageService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MoyasarPaymentService
{
    protected const MAX_RENEWAL_ATTEMPTS = 3;
    protected const RETRY_BACKOFF_MINUTES = [1440, 4320];

    protected const FINALIZED_STATUSES = [
        'paid',
        'failed',
        'authorized',
        'captured',
        'refunded',
        'voided',
        'verified',
    ];

    protected const FINAL_SUCCESS_STATUSES = ['paid', 'captured'];

    public function createCheckoutSession(array $input): array
    {
        $plan = Plan::query()->find($input['plan_id']);
        if (!$plan) {
            MessageService::abort(404, 'messages.plan.not_found');
        }

        $currency = strtoupper((string) config('services.moyasar.currency', 'SAR'));
        $amountMinor = $this->toMinorUnits((string) $plan->price, $currency);

        $merchantReferenceId = (string) Str::uuid();
        $givenId = (string) Str::uuid();

        $transaction = PaymentTransaction::query()->create([
            'user_id' => (int) $input['user_id'],
            'plan_id' => (int) $plan->id,
            'merchant_reference_id' => $merchantReferenceId,
            'given_id' => $givenId,
            'provider' => 'moyasar',
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'status' => 'pending',
        ]);

        $payload = [
            'given_id' => $givenId,
            'amount' => $amountMinor,
            'currency' => $currency,
            'description' => "Plan {$plan->id} checkout",
            'callback_url' => $input['callback_url'],
            'source' => [
                'type' => $input['source_type'],
                'token' => $input['source_token'],
            ],
            'metadata' => [
                'merchant_reference_id' => $merchantReferenceId,
                'plan_id' => (string) $plan->id,
                'user_id' => (string) $input['user_id'],
            ],
        ];

        $response = $this->createPayment($payload);

        if ($response->failed()) {
            $transaction->update([
                'status' => 'failed',
                'last_error_code' => (string) $response->status(),
                'last_error_message' => 'Create payment failed',
                'raw_response' => $response->json(),
                'finalized_at' => now(),
            ]);

            MessageService::response([
                'success' => false,
                'message' => 'Unable to create checkout session',
            ], 502);
        }

        $payment = $response->json() ?? [];
        $gatewayStatus = (string) ($payment['status'] ?? '');
        $status = $this->mapGatewayStatusToTransactionStatus($gatewayStatus);

        $transaction->update([
            'provider_payment_id' => $payment['id'] ?? null,
            'gateway_status' => $gatewayStatus,
            'status' => $status,
            'redirect_url' => $payment['source']['transaction_url'] ?? null,
            'raw_response' => $payment,
            'finalized_at' => in_array($gatewayStatus, self::FINALIZED_STATUSES, true) ? now() : null,
        ]);

        if ($transaction->subscription_id === null && in_array($gatewayStatus, self::FINAL_SUCCESS_STATUSES, true)) {
            $this->activateSubscriptionFromFirstPurchase($transaction);
        }

        return [
            'transaction_id' => $transaction->id,
            'merchant_reference_id' => $transaction->merchant_reference_id,
            'gateway_payment_id' => $transaction->provider_payment_id,
            'redirect_url' => $transaction->redirect_url,
            'gateway_status' => $gatewayStatus,
            'status' => $transaction->status,
            'finalized' => in_array($gatewayStatus, self::FINALIZED_STATUSES, true),
            'verified' => in_array($gatewayStatus, self::FINAL_SUCCESS_STATUSES, true),
            'expires_at' => null,
        ];
    }

    public function purchasePlanForUser(int $userId, int $planId): array
    {
        $paymentMethod = SavedPaymentMethod::query()
            ->where('user_id', $userId)
            ->where('provider', 'moyasar')
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if (!$paymentMethod) {
            MessageService::abort(422, 'Default active payment method is required');
        }

        return $this->createCheckoutSession([
            'user_id' => $userId,
            'plan_id' => $planId,
            'source_type' => 'token',
            'source_token' => (string) $paymentMethod->token,
            'callback_url' => rtrim((string) config('app.url'), '/') . '/billing/callback',
        ]);
    }

    public function retryCurrentSubscription(int $userId): array
    {
        $subscription = Subscription::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['active', 'past_due'])
            ->whereDate('end_date', '<=', now()->toDateString())
            ->where('auto_renew', true)
            ->where(function ($query) {
                $query->whereNull('cancel_at_period_end')->orWhere('cancel_at_period_end', false);
            })
            ->orderByDesc('id')
            ->first();

        if (!$subscription) {
            MessageService::abort(404, 'No renewable subscription found for retry');
        }

        $blockedReason = null;
        $result = $this->attemptRenewal(
            subscription: $subscription,
            trigger: 'user_retry',
            allowBackoffOverride: true,
            blockedReason: $blockedReason
        );
        if (!$result) {
            MessageService::response([
                'success' => false,
                'message' => 'Renewal attempt is not currently allowed',
                'key' => 'billing.retry.' . ($blockedReason ?? 'unknown'),
                'reason_code' => $blockedReason ?? 'unknown',
            ], 422);
        }

        return $result;
    }

    public function processDueRenewalsBatch(int $limit = 100): array
    {
        $subscriptions = Subscription::query()
            ->whereIn('status', ['active', 'past_due'])
            ->whereDate('end_date', '<=', now()->toDateString())
            ->where('auto_renew', true)
            ->where(function ($query) {
                $query->whereNull('cancel_at_period_end')->orWhere('cancel_at_period_end', false);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        $created = 0;
        foreach ($subscriptions as $subscription) {
            $processed++;
            $attempt = $this->attemptRenewal($subscription, 'scheduler');
            if ($attempt) {
                $created++;
            }
        }

        return [
            'processed_subscriptions' => $processed,
            'created_attempts' => $created,
        ];
    }

    public function attemptRenewal(
        Subscription $subscription,
        string $trigger,
        bool $allowBackoffOverride = false,
        ?string &$blockedReason = null
    ): ?array
    {
        $subscription->loadMissing('plan');
        $plan = $subscription->plan;
        if (!$plan) {
            $blockedReason = 'plan_not_found';
            return null;
        }

        $currency = strtoupper((string) config('services.moyasar.currency', 'SAR'));
        $amountMinor = $this->toMinorUnits((string) $plan->price, $currency);

        [$periodStart, $periodEnd] = $this->computeRenewalPeriod($subscription, $plan->interval);

        $latestAttempt = PaymentTransaction::query()
            ->where('provider', 'moyasar')
            ->where('subscription_id', $subscription->id)
            ->whereDate('billing_period_start', $periodStart->toDateString())
            ->whereDate('billing_period_end', $periodEnd->toDateString())
            ->orderByDesc('attempt_no')
            ->first();

        if ($latestAttempt && $latestAttempt->status === 'paid') {
            $blockedReason = 'already_paid_for_period';
            return null;
        }

        $attemptNo = $latestAttempt ? ((int) $latestAttempt->attempt_no + 1) : 1;
        if ($attemptNo > self::MAX_RENEWAL_ATTEMPTS) {
            $blockedReason = 'max_attempts_reached';
            return null;
        }

        if (
            !$allowBackoffOverride &&
            $latestAttempt &&
            $latestAttempt->next_retry_at &&
            Carbon::parse($latestAttempt->next_retry_at)->isFuture()
        ) {
            $blockedReason = 'backoff_not_elapsed';
            return null;
        }

        $paymentMethod = SavedPaymentMethod::query()
            ->where('user_id', $subscription->user_id)
            ->where('provider', 'moyasar')
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if (!$paymentMethod) {
            $subscription->update(['status' => 'past_due']);
            $blockedReason = 'no_active_payment_method';
            return null;
        }

        $merchantReferenceId = (string) Str::uuid();
        $givenId = (string) Str::uuid();

        $transaction = PaymentTransaction::query()->create([
            'user_id' => (int) $subscription->user_id,
            'plan_id' => (int) $plan->id,
            'subscription_id' => (int) $subscription->id,
            'merchant_reference_id' => $merchantReferenceId,
            'given_id' => $givenId,
            'provider' => 'moyasar',
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'attempt_no' => $attemptNo,
            'billing_period_start' => $periodStart->toDateString(),
            'billing_period_end' => $periodEnd->toDateString(),
            'status' => 'pending',
        ]);

        $payload = [
            'given_id' => $givenId,
            'amount' => $amountMinor,
            'currency' => $currency,
            'description' => "Renewal subscription {$subscription->id}",
            'source' => [
                'type' => 'token',
                'token' => $paymentMethod->token,
            ],
            'metadata' => [
                'merchant_reference_id' => $merchantReferenceId,
                'subscription_id' => (string) $subscription->id,
                'plan_id' => (string) $plan->id,
                'user_id' => (string) $subscription->user_id,
                'trigger' => $trigger,
            ],
        ];

        $response = $this->createPayment($payload);
        if ($response->failed()) {
            $nextRetryAt = $this->computeNextRetryAt($attemptNo);
            $transaction->update([
                'status' => 'failed',
                'gateway_status' => 'failed',
                'last_error_code' => (string) $response->status(),
                'last_error_message' => 'Create payment failed',
                'raw_response' => $response->json(),
                'next_retry_at' => $nextRetryAt,
                'finalized_at' => now(),
            ]);

            $subscription->update([
                'status' => $attemptNo >= self::MAX_RENEWAL_ATTEMPTS ? 'expired' : 'past_due',
                'auto_renew' => $attemptNo >= self::MAX_RENEWAL_ATTEMPTS ? false : $subscription->auto_renew,
            ]);

            return [
                'transaction_id' => $transaction->id,
                'merchant_reference_id' => $transaction->merchant_reference_id,
                'gateway_status' => 'failed',
                'status' => $transaction->status,
                'finalized' => true,
                'verified' => false,
            ];
        }

        $payment = $response->json() ?? [];
        $gatewayStatus = (string) ($payment['status'] ?? '');
        $status = $this->mapGatewayStatusToTransactionStatus($gatewayStatus);

        $transaction->update([
            'provider_payment_id' => $payment['id'] ?? null,
            'gateway_status' => $gatewayStatus,
            'status' => $status,
            'redirect_url' => $payment['source']['transaction_url'] ?? null,
            'raw_response' => $this->sanitizeGatewayResponse($payment),
            'next_retry_at' => $status === 'failed' ? $this->computeNextRetryAt($attemptNo) : null,
            'finalized_at' => in_array($gatewayStatus, self::FINALIZED_STATUSES, true) ? now() : null,
        ]);

        if ($status === 'failed') {
            $subscription->update([
                'status' => $attemptNo >= self::MAX_RENEWAL_ATTEMPTS ? 'expired' : 'past_due',
                'auto_renew' => $attemptNo >= self::MAX_RENEWAL_ATTEMPTS ? false : $subscription->auto_renew,
            ]);
        }

        return [
            'transaction_id' => $transaction->id,
            'merchant_reference_id' => $transaction->merchant_reference_id,
            'gateway_payment_id' => $transaction->provider_payment_id,
            'gateway_status' => $gatewayStatus,
            'status' => $transaction->status,
            'finalized' => in_array($gatewayStatus, self::FINALIZED_STATUSES, true),
            'verified' => $status === 'paid',
        ];
    }

    public function verify(array $input): array
    {
        $transaction = PaymentTransaction::query()
            ->where('provider', 'moyasar')
            ->where('merchant_reference_id', $input['merchant_reference_id'])
            ->where('user_id', (int) $input['user_id'])
            ->first();

        if (!$transaction) {
            MessageService::abort(404, 'Payment transaction not found');
        }

        if (empty($transaction->provider_payment_id)) {
            MessageService::abort(422, 'Payment transaction is not linked to gateway payment yet');
        }

        $payment = $this->fetchPayment($transaction->provider_payment_id);
        $mismatches = $this->collectMismatches(
            payment: $payment,
            expectedAmountMinor: (int) $transaction->amount_minor,
            expectedCurrency: strtoupper((string) $transaction->currency),
            expectedPlanId: (int) $transaction->plan_id,
            expectedUserId: (int) $transaction->user_id,
            expectedMerchantReferenceId: (string) $transaction->merchant_reference_id
        );

        $gatewayStatus = (string) ($payment['status'] ?? '');
        $finalized = in_array($gatewayStatus, self::FINALIZED_STATUSES, true);
        $verified = empty($mismatches) && $gatewayStatus === 'paid';

        if ($finalized && !empty($transaction->provider_payment_id)) {
            $this->finalizeByGatewayPaymentId(
                paymentId: (string) $transaction->provider_payment_id,
                gatewayStatus: $gatewayStatus,
                rawPayload: $payment
            );
        }

        return [
            'merchant_reference_id' => (string) $transaction->merchant_reference_id,
            'verified' => $verified,
            'finalized' => $finalized,
            'gateway_status' => $gatewayStatus,
        ];
    }

    public function finalizeByGatewayPaymentId(string $paymentId, string $gatewayStatus, array $rawPayload = []): void
    {
        DB::transaction(function () use ($paymentId, $gatewayStatus, $rawPayload) {
            /** @var PaymentTransaction|null $transaction */
            $transaction = PaymentTransaction::query()
                ->where('provider', 'moyasar')
                ->where('provider_payment_id', $paymentId)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                return;
            }

            $previousStatus = (string) $transaction->status;
            $nextStatus = $this->mapGatewayStatusToTransactionStatus($gatewayStatus);

            $transaction->update([
                'gateway_status' => $gatewayStatus,
                'status' => $nextStatus,
                'raw_response' => !empty($rawPayload) ? $this->sanitizeGatewayResponse($rawPayload) : $transaction->raw_response,
                'finalized_at' => in_array($gatewayStatus, self::FINALIZED_STATUSES, true) ? now() : null,
            ]);

            if ($transaction->subscription_id) {
                $subscription = Subscription::query()
                    ->lockForUpdate()
                    ->find($transaction->subscription_id);

                if ($subscription) {
                    if ($nextStatus === 'paid' && $previousStatus !== 'paid') {
                        $subscription->loadMissing('plan');
                        $newEndDate = $this->computeNextEndDate(
                            Carbon::parse($subscription->end_date),
                            $subscription->plan?->interval ?? 'monthly'
                        );

                        $subscription->update([
                            'status' => 'active',
                            'start_date' => Carbon::parse($subscription->end_date)->addDay()->toDateString(),
                            'end_date' => $newEndDate->toDateString(),
                        ]);
                    } elseif ($nextStatus === 'failed') {
                        $subscription->update(['status' => 'past_due']);
                    }
                }
            } elseif (in_array($nextStatus, self::FINAL_SUCCESS_STATUSES, true)) {
                $this->activateSubscriptionFromFirstPurchase($transaction);
            }
        });
    }

    protected function activateSubscriptionFromFirstPurchase(PaymentTransaction $transaction): void
    {
        $plan = Plan::query()->find($transaction->plan_id);
        if (!$plan) {
            return;
        }

        $periodStart = now()->startOfDay();
        $periodEnd = $this->computeNextEndDate($periodStart->copy()->subDay(), (string) $plan->interval);

        $subscription = Subscription::query()
            ->where('user_id', $transaction->user_id)
            ->orderByDesc('id')
            ->first();

        $payload = [
            'plan_id' => (int) $plan->id,
            'status' => 'active',
            'start_date' => $periodStart->toDateString(),
            'end_date' => $periodEnd->toDateString(),
            'price' => (string) $plan->price,
            'currency' => strtoupper((string) config('services.moyasar.currency', 'SAR')),
            'auto_renew' => true,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ];

        if ($subscription) {
            $subscription->update($payload);
        } else {
            Subscription::query()->create([
                'user_id' => (int) $transaction->user_id,
                ...$payload,
            ]);
        }
    }

    protected function fetchPayment(string $paymentId): array
    {
        $baseUrl = rtrim((string) config('services.moyasar.base_url'), '/');
        $secretKey = (string) config('services.moyasar.secret_key');

        if ($baseUrl === '' || $secretKey === '') {
            MessageService::abort(500, 'Moyasar credentials are not configured');
        }

        /** @var Response $response */
        $response = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->withBasicAuth($secretKey, '')
            ->get("{$baseUrl}/payments/{$paymentId}");

        if ($response->status() === 404) {
            MessageService::abort(404, 'Moyasar payment not found');
        }

        if ($response->failed()) {
            MessageService::response([
                'success' => false,
                'message' => 'Failed to fetch payment from Moyasar',
                'status_code' => $response->status(),
            ], 502);
        }

        return $response->json() ?? [];
    }

    protected function createPayment(array $payload): Response
    {
        $baseUrl = rtrim((string) config('services.moyasar.base_url'), '/');
        $secretKey = (string) config('services.moyasar.secret_key');

        if ($baseUrl === '' || $secretKey === '') {
            MessageService::abort(500, 'Moyasar credentials are not configured');
        }

        /** @var Response $response */
        $response = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->withBasicAuth($secretKey, '')
            ->post("{$baseUrl}/payments", $payload);

        return $response;
    }

    protected function collectMismatches(
        array $payment,
        int $expectedAmountMinor,
        string $expectedCurrency,
        int $expectedPlanId,
        int $expectedUserId,
        string $expectedMerchantReferenceId
    ): array {
        $mismatches = [];

        if ((int) ($payment['amount'] ?? -1) !== $expectedAmountMinor) {
            $mismatches[] = [
                'field' => 'amount',
                'expected' => $expectedAmountMinor,
                'actual' => (int) ($payment['amount'] ?? -1),
            ];
        }

        if (strtoupper((string) ($payment['currency'] ?? '')) !== $expectedCurrency) {
            $mismatches[] = [
                'field' => 'currency',
                'expected' => $expectedCurrency,
                'actual' => strtoupper((string) ($payment['currency'] ?? '')),
            ];
        }

        // Metadata checks are optional and only applied when keys exist.
        $actualMetadata = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
        if (array_key_exists('plan_id', $actualMetadata) && (int) $actualMetadata['plan_id'] !== $expectedPlanId) {
            $mismatches[] = [
                'field' => 'metadata.plan_id',
                'expected' => $expectedPlanId,
                'actual' => $actualMetadata['plan_id'],
            ];
        }
        if (array_key_exists('user_id', $actualMetadata) && (int) $actualMetadata['user_id'] !== $expectedUserId) {
            $mismatches[] = [
                'field' => 'metadata.user_id',
                'expected' => $expectedUserId,
                'actual' => $actualMetadata['user_id'],
            ];
        }
        if (array_key_exists('merchant_reference_id', $actualMetadata) && (string) $actualMetadata['merchant_reference_id'] !== $expectedMerchantReferenceId) {
            $mismatches[] = [
                'field' => 'metadata.merchant_reference_id',
                'expected' => $expectedMerchantReferenceId,
                'actual' => $actualMetadata['merchant_reference_id'],
            ];
        }

        return $mismatches;
    }

    protected function toMinorUnits(string $amount, string $currency): int
    {
        // Most supported currencies in current rollout are two-decimal currencies.
        $scale = $currency === 'JPY' ? 0 : 2;

        return (int) round(((float) $amount) * (10 ** $scale));
    }

    protected function mapGatewayStatusToTransactionStatus(string $gatewayStatus): string
    {
        return match ($gatewayStatus) {
            'paid', 'captured' => 'paid',
            'failed', 'voided' => 'failed',
            'authorized' => 'authorized',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }

    protected function computeRenewalPeriod(Subscription $subscription, string $interval): array
    {
        $periodStart = Carbon::parse($subscription->end_date)->addDay()->startOfDay();
        $periodEnd = $this->computeNextEndDate($periodStart->copy()->subDay(), $interval);

        return [$periodStart, $periodEnd];
    }

    protected function computeNextEndDate(Carbon $currentEndDate, string $interval): Carbon
    {
        return match ($interval) {
            'annual' => $currentEndDate->copy()->addYear(),
            'semi_annual' => $currentEndDate->copy()->addMonths(6),
            default => $currentEndDate->copy()->addMonth(),
        };
    }

    protected function computeNextRetryAt(int $attemptNo): ?Carbon
    {
        $index = $attemptNo - 1;
        if (!isset(self::RETRY_BACKOFF_MINUTES[$index])) {
            return null;
        }

        return now()->addMinutes(self::RETRY_BACKOFF_MINUTES[$index]);
    }

    protected function sanitizeGatewayResponse(array $payload): array
    {
        // Keep only non-sensitive fields needed for audit/reconciliation.
        return [
            'id' => $payload['id'] ?? null,
            'status' => $payload['status'] ?? null,
            'amount' => $payload['amount'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'created_at' => $payload['created_at'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
            'source' => [
                'type' => $payload['source']['type'] ?? null,
                'company' => $payload['source']['company'] ?? null,
                'number' => $payload['source']['number'] ?? null,
                'reference_number' => $payload['source']['reference_number'] ?? null,
                'message' => $payload['source']['message'] ?? null,
            ],
        ];
    }
}

