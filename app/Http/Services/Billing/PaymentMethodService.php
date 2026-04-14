<?php

namespace App\Http\Services\Billing;

use App\Models\Billing\PaymentTransaction;
use App\Models\Billing\RefundTransaction;
use App\Models\Billing\SavedPaymentMethod;
use App\Support\MoyasarConfig;
use App\Services\MessageService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaymentMethodService
{
    protected const FINALIZED_PAYMENT_STATUSES = [
        'paid',
        'failed',
        'authorized',
        'captured',
        'refunded',
        'voided',
        'verified',
        'partially_refunded',
    ];

    public function listForUser(int $userId)
    {
        $methods = SavedPaymentMethod::query()
            ->where('user_id', $userId)
            ->where('provider', 'moyasar')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        $defaultMethod = $methods->firstWhere('is_default', true);
        $publicMethods = $methods->map(fn (SavedPaymentMethod $method) => [
            'id' => (int) $method->id,
            'provider' => (string) $method->provider,
            'status' => (string) $method->status,
            'brand' => $method->brand,
            'last4' => $method->last4,
            'exp_month' => $method->exp_month,
            'exp_year' => $method->exp_year,
            'is_default' => (bool) $method->is_default,
            'verification_refund' => is_array($method->meta) ? ($method->meta['verification_refund'] ?? null) : null,
            'created_at' => optional($method->created_at)->toIso8601String(),
        ])->values();

        return [
            'methods' => $publicMethods,
            'has_default_payment_method' => $defaultMethod !== null,
            'default_method_status' => $defaultMethod?->status,
            'can_retry' => $defaultMethod !== null && $defaultMethod->status === 'active',
        ];
    }

    public function storeForUser(int $userId, array $input): SavedPaymentMethod
    {
        $resolved = $this->resolveMethodInput($userId, $input);
        $isDefault = (bool) ($input['is_default'] ?? false);

        if ($isDefault) {
            SavedPaymentMethod::query()
                ->where('user_id', $userId)
                ->where('provider', 'moyasar')
                ->update(['is_default' => false]);
        }

        $method = SavedPaymentMethod::query()->updateOrCreate(
            [
                'provider' => 'moyasar',
                'token' => $resolved['token'],
            ],
            [
                'user_id' => $userId,
                'status' => $resolved['status'] ?? 'active',
                'brand' => $resolved['brand'] ?? null,
                'last4' => $resolved['last4'] ?? null,
                'exp_month' => $resolved['exp_month'] ?? null,
                'exp_year' => $resolved['exp_year'] ?? null,
                'is_default' => $isDefault,
                'meta' => $resolved['meta'] ?? null,
            ]
        );

        if (!$method->is_default) {
            $hasDefault = SavedPaymentMethod::query()
                ->where('user_id', $userId)
                ->where('provider', 'moyasar')
                ->where('status', 'active')
                ->where('is_default', true)
                ->exists();

            if (!$hasDefault) {
                $method->update(['is_default' => true]);
            }
        }

        $this->refundVerificationIfRequested($userId, $input, $method);

        return $method->fresh();
    }

    public function setDefaultForUser(int $userId, int $methodId): SavedPaymentMethod
    {
        $method = SavedPaymentMethod::query()
            ->where('id', $methodId)
            ->where('user_id', $userId)
            ->where('provider', 'moyasar')
            ->first();

        if (!$method) {
            MessageService::abort(404, 'Payment method not found');
        }

        SavedPaymentMethod::query()
            ->where('user_id', $userId)
            ->where('provider', 'moyasar')
            ->update(['is_default' => false]);

        $method->update(['is_default' => true]);

        return $method->fresh();
    }

    public function deleteForUser(int $userId, int $methodId): void
    {
        $method = SavedPaymentMethod::query()
            ->where('id', $methodId)
            ->where('user_id', $userId)
            ->where('provider', 'moyasar')
            ->first();

        if (!$method) {
            MessageService::abort(404, 'Payment method not found');
        }

        $wasDefault = (bool) $method->is_default;
        $method->delete();

        if ($wasDefault) {
            $replacement = SavedPaymentMethod::query()
                ->where('user_id', $userId)
                ->where('provider', 'moyasar')
                ->where('status', 'active')
                ->orderByDesc('id')
                ->first();

            if ($replacement) {
                $replacement->update(['is_default' => true]);
            }
        }
    }

    protected function resolveMethodInput(int $userId, array $input): array
    {
        $token = trim((string) ($input['token'] ?? ''));
        if ($token !== '') {
            return [
                'token' => $token,
                'status' => $input['status'] ?? 'active',
                'brand' => $input['brand'] ?? null,
                'last4' => $input['last4'] ?? null,
                'exp_month' => $input['exp_month'] ?? null,
                'exp_year' => $input['exp_year'] ?? null,
                'meta' => $input['meta'] ?? null,
            ];
        }

        $paymentId = trim((string) ($input['gateway_payment_id'] ?? ''));
        if ($paymentId === '') {
            MessageService::abort(422, 'Either token or gateway_payment_id is required');
        }

        $payment = $this->fetchGatewayPayment($paymentId);
        $source = is_array($payment['source'] ?? null) ? $payment['source'] : [];
        $metadata = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
        $gatewayStatus = strtolower((string) ($payment['status'] ?? ''));
        $gatewaySourceType = strtolower((string) ($source['type'] ?? ''));

        if (array_key_exists('user_id', $metadata) && (int) $metadata['user_id'] !== $userId) {
            MessageService::abort(404, 'Gateway payment not found');
        }

        $this->recordGatewayPaymentAttempt(
            userId: $userId,
            paymentId: $paymentId,
            payment: $payment
        );

        if (!in_array($gatewayStatus, ['paid', 'authorized', 'captured'], true)) {
            MessageService::abort(422, 'Gateway payment is not eligible for saving payment method');
        }

        if ($gatewaySourceType !== '' && $gatewaySourceType !== 'creditcard') {
            MessageService::abort(422, 'Unsupported gateway source type for tokenization');
        }

        $gatewayToken = trim((string) ($source['token'] ?? ''));
        if ($gatewayToken === '') {
            MessageService::abort(422, 'Gateway payment does not contain reusable token');
        }

        $maskedNumber = (string) ($source['number'] ?? '');
        $digits = preg_replace('/\D+/', '', $maskedNumber);
        $last4 = is_string($digits) && strlen($digits) >= 4 ? substr($digits, -4) : null;

        $existingMeta = is_array($input['meta'] ?? null) ? $input['meta'] : [];
        $meta = array_merge($existingMeta, [
            'token_source' => 'gateway_payment_fetch',
            'gateway_payment_id' => $paymentId,
            'gateway_status' => $payment['status'] ?? null,
        ]);

        return [
            'token' => $gatewayToken,
            'status' => $input['status'] ?? 'active',
            'brand' => $input['brand'] ?? ($source['company'] ?? null),
            'last4' => $input['last4'] ?? $last4,
            'exp_month' => $input['exp_month'] ?? ($source['month'] ?? null),
            'exp_year' => $input['exp_year'] ?? ($source['year'] ?? null),
            'meta' => $meta,
        ];
    }

    protected function fetchGatewayPayment(string $paymentId): array
    {
        $baseUrl = rtrim((string) config('services.moyasar.base_url'), '/');
        $secretKey = MoyasarConfig::secretKey();

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
            MessageService::abort(404, 'Gateway payment not found');
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

    protected function refundVerificationIfRequested(int $userId, array $input, SavedPaymentMethod $method): void
    {
        $explicitRefundFlag = array_key_exists('refund_verification', $input)
            ? (bool) $input['refund_verification']
            : null;

        $paymentId = trim((string) ($input['gateway_payment_id'] ?? ''));
        if ($paymentId === '') {
            return;
        }

        $payment = $this->fetchGatewayPayment($paymentId);
        $metadata = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
        if (array_key_exists('user_id', $metadata) && (int) $metadata['user_id'] !== $userId) {
            return;
        }

        $paymentTransaction = $this->recordGatewayPaymentAttempt(
            userId: $userId,
            paymentId: $paymentId,
            payment: $payment
        );

        $status = strtolower((string) ($payment['status'] ?? ''));
        if (!in_array($status, ['paid', 'captured'], true)) {
            return;
        }

        $paymentAmount = (int) ($payment['amount'] ?? 0);
        $amount = (int) ($input['verification_amount_minor'] ?? $paymentAmount);
        if ($amount > $paymentAmount) {
            $amount = $paymentAmount;
        }
        if ($amount <= 0) {
            return;
        }

        $purpose = strtolower((string) ($metadata['purpose'] ?? ''));
        if ($purpose === 'plan_purchase') {
            return;
        }

        // Safety fallback: if client omitted refund flag, auto-refund only very small verification amounts.
        $maxVerificationMinor = (int) env('BILLING_VERIFICATION_REFUND_MAX_MINOR', 100);
        $autoRefundByAmount = $amount > 0 && $amount <= $maxVerificationMinor;
        $shouldRefund = $explicitRefundFlag ?? $autoRefundByAmount;

        if (!$shouldRefund) {
            return;
        }

        $existingMeta = is_array($method->meta) ? $method->meta : [];
        $existingRefund = is_array($existingMeta['verification_refund'] ?? null)
            ? $existingMeta['verification_refund']
            : [];
        if (($existingRefund['success'] ?? false) === true) {
            return;
        }

        $refundTransaction = RefundTransaction::query()->create([
            'payment_transaction_id' => (int) $paymentTransaction->id,
            'provider' => 'moyasar',
            'provider_payment_id' => $paymentId,
            'amount_minor' => $amount,
            'currency' => strtoupper((string) ($payment['currency'] ?? config('services.moyasar.currency', 'SAR'))),
            'status' => 'pending',
            'gateway_status' => 'pending',
            'requested_at' => now(),
        ]);

        $refundResponse = $this->createRefund($paymentId, $amount);
        $refundPayload = $refundResponse->json() ?? [];
        $refundDetails = $this->extractRefundResponseDetails($refundPayload, $paymentId);
        $refundStatus = $refundResponse->successful() ? 'processing' : 'failed';
        $refundTransaction->update([
            'provider_refund_id' => $refundDetails['refund_id'],
            'status' => $refundStatus,
            'gateway_status' => $refundDetails['gateway_status'] ?? ($refundResponse->successful() ? 'pending' : 'failed'),
            'error_code' => $refundDetails['error_code'],
            'error_message' => $refundDetails['error_message'],
            'raw_response' => !empty($refundPayload) ? $refundPayload : ['raw' => $refundResponse->body()],
            'failed_at' => $refundResponse->successful() ? null : now(),
        ]);

        $meta = is_array($method->meta) ? $method->meta : [];
        $meta['verification_refund'] = [
            'requested' => true,
            'gateway_payment_id' => $paymentId,
            'refund_transaction_id' => (int) $refundTransaction->id,
            'original_payment_amount_minor' => $paymentAmount,
            'amount_minor' => $amount,
            'status_code' => $refundResponse->status(),
            'success' => $refundResponse->successful(),
            'response' => $refundResponse->json() ?? ['raw' => $refundResponse->body()],
        ];
        if ($refundResponse->successful()) {
            $meta['verification_refund']['refunded_at'] = now()->toIso8601String();
        } else {
            $meta['verification_refund']['error'] = (string) ($refundResponse->body() ?? 'Unknown refund error');
        }
        $method->update(['meta' => $meta]);

        if ($explicitRefundFlag === true && !$refundResponse->successful()) {
            MessageService::abort(502, 'Card was saved but verification refund failed');
        }
    }

    protected function createRefund(string $paymentId, int $amountMinor): Response
    {
        $baseUrl = rtrim((string) config('services.moyasar.base_url'), '/');
        $secretKey = MoyasarConfig::secretKey();

        /** @var Response $response */
        $response = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->withBasicAuth($secretKey, '')
            ->post("{$baseUrl}/payments/{$paymentId}/refund", [
                'amount' => $amountMinor,
            ]);

        // Same payment refund endpoint fallback without amount payload.
        if ($response->failed()) {
            $response = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->withBasicAuth($secretKey, '')
                ->post("{$baseUrl}/payments/{$paymentId}/refund");
        }

        // Backward/compat fallback for gateway variants.
        if ($response->failed()) {
            $response = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->withBasicAuth($secretKey, '')
                ->post("{$baseUrl}/payments/{$paymentId}/refunds", [
                    'amount' => $amountMinor,
                ]);
        }

        if ($response->failed()) {
            $response = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->withBasicAuth($secretKey, '')
                ->post("{$baseUrl}/payments/{$paymentId}/refunds");
        }

        return $response;
    }

    protected function recordGatewayPaymentAttempt(int $userId, string $paymentId, array $payment): PaymentTransaction
    {
        $metadata = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
        $gatewayStatus = strtolower((string) ($payment['status'] ?? ''));
        $status = $this->mapGatewayStatusToTransactionStatus($gatewayStatus);
        $failure = $status === 'failed'
            ? $this->extractFailureDetails($payment)
            : ['code' => null, 'message' => null];

        $planId = isset($metadata['plan_id']) && is_numeric($metadata['plan_id'])
            ? (int) $metadata['plan_id']
            : null;
        $currency = strtoupper((string) ($payment['currency'] ?? config('services.moyasar.currency', 'SAR')));
        $amountMinor = max(0, (int) ($payment['amount'] ?? 0));

        $attributes = [
            'plan_id' => $planId,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'status' => $status,
            'gateway_status' => $gatewayStatus,
            'raw_response' => $this->sanitizeGatewayResponse($payment),
            'last_error_code' => $failure['code'],
            'last_error_message' => $failure['message'],
            'finalized_at' => in_array($gatewayStatus, self::FINALIZED_PAYMENT_STATUSES, true) ? now() : null,
        ];

        $transaction = PaymentTransaction::query()
            ->where('provider', 'moyasar')
            ->where('provider_payment_id', $paymentId)
            ->first();

        if ($transaction) {
            if ((int) $transaction->user_id !== $userId) {
                MessageService::abort(404, 'Payment transaction not found');
            }
            $transaction->update($attributes);

            return $transaction->fresh();
        }

        return PaymentTransaction::query()->create([
            'user_id' => $userId,
            'plan_id' => $planId,
            'subscription_id' => null,
            'merchant_reference_id' => (string) ($metadata['merchant_reference_id'] ?? Str::uuid()),
            'given_id' => (string) ($payment['given_id'] ?? Str::uuid()),
            'provider' => 'moyasar',
            'provider_payment_id' => $paymentId,
            ...$attributes,
        ]);
    }

    protected function mapGatewayStatusToTransactionStatus(string $gatewayStatus): string
    {
        return match ($gatewayStatus) {
            'paid', 'captured' => 'paid',
            'failed', 'voided' => 'failed',
            'authorized' => 'authorized',
            'refunded', 'partially_refunded' => 'refunded',
            default => 'pending',
        };
    }

    protected function sanitizeGatewayResponse(array $payload): array
    {
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
                'response_code' => $payload['source']['response_code'] ?? null,
            ],
        ];
    }

    /**
     * @return array{code: ?string, message: ?string}
     */
    protected function extractFailureDetails(array $payload, ?string $fallbackCode = null, ?string $fallbackMessage = null): array
    {
        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];

        $code = $this->toNullableString($source['response_code'] ?? null)
            ?? $this->toNullableString($payload['response_code'] ?? null)
            ?? $this->toNullableString($payload['error']['code'] ?? null)
            ?? $this->toNullableString($payload['errors']['code'] ?? null)
            ?? $this->toNullableString($fallbackCode);

        $message = $this->toNullableString($source['message'] ?? null)
            ?? $this->toNullableString($payload['message'] ?? null)
            ?? $this->toNullableString($payload['error']['message'] ?? null)
            ?? $this->toNullableString($fallbackMessage);

        return [
            'code' => $code,
            'message' => $message,
        ];
    }

    /**
     * @return array{refund_id: ?string, gateway_status: ?string, error_code: ?string, error_message: ?string}
     */
    protected function extractRefundResponseDetails(array $payload, string $paymentId): array
    {
        $refundId = $this->toNullableString($payload['id'] ?? null);
        if ($refundId === $paymentId) {
            $refundId = $this->toNullableString($payload['refund_id'] ?? null)
                ?? $this->toNullableString($payload['refund']['id'] ?? null);
        }

        $error = $this->extractFailureDetails($payload);

        return [
            'refund_id' => $refundId,
            'gateway_status' => $this->toNullableString($payload['status'] ?? null),
            'error_code' => $error['code'],
            'error_message' => $error['message'],
        ];
    }

    protected function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

