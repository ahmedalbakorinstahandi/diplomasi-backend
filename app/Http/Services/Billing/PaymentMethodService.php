<?php

namespace App\Http\Services\Billing;

use App\Models\Billing\SavedPaymentMethod;
use App\Services\MessageService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PaymentMethodService
{
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
        $shouldRefund = (bool) ($input['refund_verification'] ?? false);
        $paymentId = trim((string) ($input['gateway_payment_id'] ?? ''));
        if (!$shouldRefund || $paymentId === '') {
            return;
        }

        $payment = $this->fetchGatewayPayment($paymentId);
        $metadata = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
        if (array_key_exists('user_id', $metadata) && (int) $metadata['user_id'] !== $userId) {
            return;
        }

        $status = strtolower((string) ($payment['status'] ?? ''));
        if (!in_array($status, ['paid', 'captured'], true)) {
            return;
        }

        $amount = (int) ($input['verification_amount_minor'] ?? $payment['amount'] ?? 0);
        if ($amount <= 0) {
            return;
        }

        $refundResponse = $this->createRefund($paymentId, $amount);
        $meta = is_array($method->meta) ? $method->meta : [];
        $meta['verification_refund'] = [
            'requested' => true,
            'amount_minor' => $amount,
            'status_code' => $refundResponse->status(),
            'success' => $refundResponse->successful(),
        ];
        $method->update(['meta' => $meta]);
    }

    protected function createRefund(string $paymentId, int $amountMinor): Response
    {
        $baseUrl = rtrim((string) config('services.moyasar.base_url'), '/');
        $secretKey = (string) config('services.moyasar.secret_key');

        /** @var Response $response */
        $response = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->withBasicAuth($secretKey, '')
            ->post("{$baseUrl}/payments/{$paymentId}/refund", [
                'amount' => $amountMinor,
            ]);

        return $response;
    }
}

