<?php

namespace App\Http\Services\Billing;

use App\Http\Notifications\BillingNotification;
use App\Models\Billing\PaymentTransaction;
use App\Models\Billing\Plan;
use App\Models\Billing\RefundTransaction;
use App\Models\Billing\SavedPaymentMethod;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionEvent;
use App\Http\Services\Billing\PaymentMethodService;
use App\Support\MoyasarConfig;
use App\Services\MessageService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MoyasarPaymentService
{
    public function __construct(
        protected PaymentMethodService $paymentMethodService,
        protected UsdSarConversionService $conversionService
    ) {}

    protected const MAX_RENEWAL_ATTEMPTS = 3;
    protected const RETRY_BACKOFF_MINUTES = [1440, 4320];

    protected const FINALIZED_STATUSES = [
        'paid',
        'failed',
        'authorized',
        'captured',
        'refunded',
        'partially_refunded',
        'voided',
        'verified',
    ];

    protected const FINAL_SUCCESS_STATUSES = ['paid', 'captured'];

    public function createCheckoutSession(array $input): array
    {
        $userId = (int) ($input['user_id'] ?? 0);
        if ($userId && $this->hasPurchaseBlockingSubscription($userId)) {
            MessageService::response([
                'success' => false,
                'message' => 'لا يمكنك شراء باقة جديدة أثناء اشتراكك الحالي أو فترة انتظار التجديد.',
                'key' => 'billing.purchase.active_subscription_exists',
            ], 422);
        }

        $plan = Plan::query()->find($input['plan_id']);
        if (!$plan) {
            MessageService::abort(404, 'messages.plan.not_found');
        }

        $preparedTransactionId = isset($input['prepared_transaction_id'])
            ? (int) $input['prepared_transaction_id']
            : 0;

        if ($preparedTransactionId > 0) {
            $transaction = PaymentTransaction::query()
                ->where('provider', 'moyasar')
                ->where('id', $preparedTransactionId)
                ->where('user_id', (int) $input['user_id'])
                ->first();

            if (!$transaction) {
                MessageService::abort(422, 'billing.purchase.prepared_transaction_not_found');
            }

            if ($transaction->status !== 'prepared') {
                MessageService::abort(422, 'billing.purchase.prepared_transaction_invalid_state');
            }

            if ($transaction->expires_at && $transaction->expires_at->isPast()) {
                MessageService::abort(422, 'billing.purchase.prepared_transaction_expired');
            }
        } else {
            $displayUsdAmountMinor = $this->toMinorUnits((string) $plan->price, 'USD');
            $conversion = $this->conversionService->convertUsdMinorToSarMinor($displayUsdAmountMinor);

            $transaction = PaymentTransaction::query()->create([
                'user_id' => (int) $input['user_id'],
                'plan_id' => (int) $plan->id,
                'merchant_reference_id' => (string) Str::uuid(),
                'given_id' => (string) Str::uuid(),
                'provider' => 'moyasar',
                'amount_minor' => (int) ($conversion['payment_sar_amount_minor'] ?? 0), // SAR minor
                'currency' => 'SAR',
                'display_currency' => 'USD',
                'display_amount_minor' => (int) $conversion['display_usd_amount_minor'],
                'exchange_rate_usd_to_sar' => (string) $conversion['rate'],
                'exchange_rate_at' => Carbon::parse((string) ($conversion['rate_at'] ?? now()->toIso8601String())),
                'exchange_rate_source' => (string) ($conversion['source'] ?? 'frankfurter'),
                'disclaimer_version' => 'sar_only_v1',
                'expires_at' => now()->addMinutes(10),
                'status' => 'pending',
            ]);
        }

        $currency = 'SAR';
        $amountMinor = (int) $transaction->amount_minor;
        $merchantReferenceId = (string) $transaction->merchant_reference_id;
        $givenId = (string) $transaction->given_id;

        // Once we are creating the Moyasar payment, move from prepared->pending for this attempt.
        if ($transaction->status === 'prepared') {
            $transaction->update(['status' => 'pending']);
        }

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
                // Snapshot fields for audit/reconciliation
                'display_currency' => 'USD',
                'display_amount_usd_minor' => (string) ($transaction->display_amount_minor ?? 0),
                'exchange_rate_usd_to_sar' => (string) ($transaction->exchange_rate_usd_to_sar ?? ''),
                'exchange_rate_at' => optional($transaction->exchange_rate_at)->toIso8601String(),
                'disclaimer_version' => (string) ($transaction->disclaimer_version ?? 'sar_only_v1'),
            ],
        ];

        $response = $this->createPayment($payload);

        if ($response->failed()) {
            $failure = $this->extractFailureDetails($response->json() ?? [], (string) $response->status(), 'Create payment failed');
            $transaction->update([
                'status' => 'failed',
                'last_error_code' => $failure['code'],
                'last_error_message' => $failure['message'],
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
        $failure = $status === 'failed'
            ? $this->extractFailureDetails($payment)
            : ['code' => null, 'message' => null];

        $transaction->update([
            'provider_payment_id' => $payment['id'] ?? null,
            'gateway_status' => $gatewayStatus,
            'status' => $status,
            'redirect_url' => $payment['source']['transaction_url'] ?? null,
            'raw_response' => $payment,
            'last_error_code' => $failure['code'],
            'last_error_message' => $failure['message'],
            'finalized_at' => in_array($gatewayStatus, self::FINALIZED_STATUSES, true) ? now() : null,
        ]);

        if ($transaction->subscription_id === null && in_array($gatewayStatus, self::FINAL_SUCCESS_STATUSES, true)) {
            Log::channel('single')->info('[billing.checkout] First purchase (sync paid)', [
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'plan_id' => $transaction->plan_id,
            ]);
            $this->activateSubscriptionFromFirstPurchase($transaction);
            try {
                $invoiceService = app(InvoiceService::class);
                $billingEmailService = app(BillingEmailService::class);
                $freshTransaction = $transaction->fresh();
                if ($freshTransaction) {
                    $invoice = $invoiceService->issueFromTransaction($freshTransaction);
                    $billingEmailService->queueInvoiceIssued($invoice);
                    Log::channel('single')->info('[billing.checkout] First purchase invoice queued', [
                        'invoice_id' => $invoice->id,
                        'transaction_id' => $freshTransaction->id,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::channel('single')->error('[billing.checkout] First purchase invoice failed', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
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
            'failure_code' => $transaction->last_error_code,
            'failure_reason' => $transaction->last_error_message,
        ];
    }

    public function purchasePlanForUser(
        int $userId,
        int $planId,
        ?int $paymentMethodId = null,
        ?int $preparedTransactionId = null
    ): array
    {
        if ($this->hasPurchaseBlockingSubscription($userId)) {
            MessageService::response([
                'success' => false,
                'message' => 'Current subscription is still active',
                'key' => 'billing.purchase.active_subscription_exists',
            ], 422);
        }

        $paymentMethodQuery = SavedPaymentMethod::query()
            ->where('user_id', $userId)
            ->where('provider', 'moyasar')
            ->where('status', 'active');

        if ($paymentMethodId !== null) {
            $paymentMethod = (clone $paymentMethodQuery)
                ->where('id', $paymentMethodId)
                ->first();

            if (!$paymentMethod) {
                MessageService::response([
                    'success' => false,
                    'message' => 'Selected payment method is not active or not owned by user',
                    'key' => 'billing.purchase.invalid_payment_method',
                ], 422);
            }
        } else {
            $paymentMethod = $paymentMethodQuery
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->first();
        }

        if (!$paymentMethod) {
            MessageService::response([
                'success' => false,
                'message' => 'Default active payment method is required',
                'key' => 'billing.purchase.no_active_payment_method',
            ], 422);
        }

        return $this->createCheckoutSession([
            'user_id' => $userId,
            'plan_id' => $planId,
            'prepared_transaction_id' => $preparedTransactionId,
            'source_type' => 'token',
            'source_token' => (string) $paymentMethod->token,
            'callback_url' => rtrim((string) config('app.url'), '/') . '/billing/callback',
        ]);
    }

    public function purchasePlanWithGatewayPayment(
        int $userId,
        int $planId,
        string $gatewayPaymentId,
        array $paymentMethodInput = []
    ): array {
        if ($this->hasPurchaseBlockingSubscription($userId)) {
            MessageService::response([
                'success' => false,
                'message' => 'Current subscription is still active',
                'key' => 'billing.purchase.active_subscription_exists',
            ], 422);
        }

        $plan = Plan::query()->find($planId);
        if (!$plan) {
            MessageService::abort(404, 'messages.plan.not_found');
        }

        $payment = $this->fetchPayment($gatewayPaymentId);
        $gatewayStatus = (string) ($payment['status'] ?? '');

        $metadata = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
        $merchantReferenceId = isset($metadata['merchant_reference_id'])
            ? (string) $metadata['merchant_reference_id']
            : '';

        $transaction = null;
        if ($merchantReferenceId !== '') {
            $transaction = PaymentTransaction::query()
                ->where('provider', 'moyasar')
                ->where('merchant_reference_id', $merchantReferenceId)
                ->where('user_id', $userId)
                ->first();
        }

        if (!$transaction) {
            MessageService::response([
                'success' => false,
                'message' => 'Prepared payment snapshot not found',
                'key' => 'billing.purchase.prepared_transaction_not_found',
            ], 422);
        }

        if ($transaction->expires_at && $transaction->expires_at->isPast()) {
            MessageService::response([
                'success' => false,
                'message' => 'Prepared payment snapshot expired',
                'key' => 'billing.purchase.prepared_transaction_expired',
            ], 422);
        }

        $expectedAmountMinor = (int) $transaction->amount_minor; // SAR minor (payment)
        $expectedCurrency = strtoupper((string) $transaction->currency);
        $actualAmountMinor = (int) ($payment['amount'] ?? -1);
        $actualCurrency = strtoupper((string) ($payment['currency'] ?? ''));

        if ($actualAmountMinor !== $expectedAmountMinor || $actualCurrency !== $expectedCurrency) {
            MessageService::response([
                'success' => false,
                'message' => 'Gateway payment amount/currency mismatch',
                'key' => 'billing.purchase.amount_currency_mismatch',
            ], 422);
        }

        // Ensure plan id matches the prepared snapshot.
        if ((int) $transaction->plan_id !== (int) $plan->id) {
            $transaction->update(['plan_id' => (int) $plan->id]);
        }

        $transaction->update(['provider_payment_id' => $gatewayPaymentId, 'status' => 'pending']);

        $mappedStatus = $this->mapGatewayStatusToTransactionStatus($gatewayStatus);
        $failure = $mappedStatus === 'failed'
            ? $this->extractFailureDetails($payment)
            : ['code' => null, 'message' => null];
        $transaction->update([
            'gateway_status' => $gatewayStatus,
            'status' => $mappedStatus,
            'raw_response' => $this->sanitizeGatewayResponse($payment),
            'last_error_code' => $failure['code'],
            'last_error_message' => $failure['message'],
            'finalized_at' => in_array($gatewayStatus, self::FINALIZED_STATUSES, true) ? now() : null,
        ]);

        $methodPayload = array_filter([
            'token' => $paymentMethodInput['token'] ?? null,
            'gateway_payment_id' => $gatewayPaymentId,
            'status' => $paymentMethodInput['status'] ?? 'active',
            'brand' => $paymentMethodInput['brand'] ?? null,
            'last4' => $paymentMethodInput['last4'] ?? null,
            'exp_month' => $paymentMethodInput['exp_month'] ?? null,
            'exp_year' => $paymentMethodInput['exp_year'] ?? null,
            'is_default' => $paymentMethodInput['is_default'] ?? true,
            'refund_verification' => false,
            'meta' => $paymentMethodInput['meta'] ?? null,
        ], fn ($v) => $v !== null);
        $this->paymentMethodService->storeForUser($userId, $methodPayload);

        if (in_array($gatewayStatus, self::FINAL_SUCCESS_STATUSES, true)) {
            $this->activateSubscriptionFromFirstPurchase($transaction->fresh());
        }

        return [
            'transaction_id' => $transaction->id,
            'merchant_reference_id' => $transaction->merchant_reference_id,
            'gateway_payment_id' => $transaction->provider_payment_id,
            'gateway_status' => $gatewayStatus,
            'status' => $mappedStatus,
            'finalized' => in_array($gatewayStatus, self::FINALIZED_STATUSES, true),
            'verified' => in_array($gatewayStatus, self::FINAL_SUCCESS_STATUSES, true),
            'failure_code' => $transaction->last_error_code,
            'failure_reason' => $transaction->last_error_message,
        ];
    }

    public function retryCurrentSubscription(int $userId): array
    {
        $subscription = Subscription::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['active', 'past_due'])
            ->where('end_date', '<=', now())
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
            ->where('end_date', '<=', now())
            ->where('auto_renew', true)
            ->where(function ($query) {
                $query->whereNull('cancel_at_period_end')->orWhere('cancel_at_period_end', false);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        // #region agent log
        $countEndDateDue = Subscription::query()->whereIn('status', ['active', 'past_due'])->where('end_date', '<=', now())->count();
        $countDueWithAutoRenew = Subscription::query()->whereIn('status', ['active', 'past_due'])->where('end_date', '<=', now())->where('auto_renew', true)->count();
        $logPath = function_exists('storage_path') ? storage_path('logs/debug-3f6903.log') : base_path('../../debug-3f6903.log');
        if (is_string($logPath) && $logPath !== '') {
            $payload = ['sessionId' => '3f6903', 'runId' => 'renewal', 'hypothesisId' => 'A', 'location' => 'MoyasarPaymentService.php:processDueRenewalsBatch', 'message' => 'Renewal due counts', 'data' => ['due_subscription_ids' => $subscriptions->pluck('id')->values()->all(), 'count_end_date_due' => $countEndDateDue, 'count_due_with_auto_renew' => $countDueWithAutoRenew, 'due_count' => $subscriptions->count()], 'timestamp' => (int) round(microtime(true) * 1000)];
            @file_put_contents($logPath, json_encode($payload) . "\n", \FILE_APPEND | \LOCK_EX);
        }
        // #endregion

        if ($subscriptions->isEmpty()) {
            Log::channel('single')->info('[billing.renewal] No subscriptions due (end_date > now or not auto_renew)', []);
        }
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
        Log::channel('single')->info('[billing.renewal] Attempt start', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'trigger' => $trigger,
        ]);

        $subscription->loadMissing('plan');
        $plan = $subscription->plan;
        if (!$plan) {
            $blockedReason = 'plan_not_found';
            Log::channel('single')->warning('[billing.renewal] Blocked: plan_not_found', ['subscription_id' => $subscription->id]);
            return null;
        }

        $displayUsdAmountMinor = $this->toMinorUnits((string) $plan->price, 'USD');
        $conversion = $this->conversionService->convertUsdMinorToSarMinor($displayUsdAmountMinor);
        $amountMinor = (int) ($conversion['payment_sar_amount_minor'] ?? 0); // SAR minor
        $currency = 'SAR';

        [$periodStart, $periodEnd] = $this->computeRenewalPeriod($subscription, $plan->interval);

        $latestAttempt = PaymentTransaction::query()
            ->where('provider', 'moyasar')
            ->where('subscription_id', $subscription->id)
            ->where('billing_period_start', $periodStart->toDateTimeString())
            ->where('billing_period_end', $periodEnd->toDateTimeString())
            ->orderByDesc('attempt_no')
            ->first();

        if ($latestAttempt && $latestAttempt->status === 'paid') {
            $blockedReason = 'already_paid_for_period';
            Log::channel('single')->info('[billing.renewal] Blocked: already_paid_for_period', ['subscription_id' => $subscription->id]);
            return null;
        }

        $attemptNo = $latestAttempt ? ((int) $latestAttempt->attempt_no + 1) : 1;
        if ($attemptNo > self::MAX_RENEWAL_ATTEMPTS) {
            $blockedReason = 'max_attempts_reached';
            Log::channel('single')->warning('[billing.renewal] Blocked: max_attempts_reached', ['subscription_id' => $subscription->id]);
            return null;
        }

        if (
            !$allowBackoffOverride &&
            $latestAttempt &&
            $latestAttempt->next_retry_at &&
            Carbon::parse($latestAttempt->next_retry_at)->isFuture()
        ) {
            $blockedReason = 'backoff_not_elapsed';
            Log::channel('single')->info('[billing.renewal] Blocked: backoff_not_elapsed', [
                'subscription_id' => $subscription->id,
                'next_retry_at' => $latestAttempt->next_retry_at,
            ]);
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
            Log::channel('single')->warning('[billing.renewal] Blocked: no_active_payment_method', ['subscription_id' => $subscription->id]);
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
            'display_currency' => 'USD',
            'display_amount_minor' => (int) $conversion['display_usd_amount_minor'],
            'exchange_rate_usd_to_sar' => (string) $conversion['rate'],
            'exchange_rate_at' => Carbon::parse((string) ($conversion['rate_at'] ?? now()->toIso8601String())),
            'exchange_rate_source' => (string) ($conversion['source'] ?? 'frankfurter'),
            'disclaimer_version' => 'sar_only_v1',
            'attempt_no' => $attemptNo,
            'billing_period_start' => $periodStart,
            'billing_period_end' => $periodEnd,
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
                'display_currency' => 'USD',
                'display_amount_usd_minor' => (string) $transaction->display_amount_minor,
                'exchange_rate_usd_to_sar' => (string) $transaction->exchange_rate_usd_to_sar,
                'exchange_rate_at' => optional($transaction->exchange_rate_at)->toIso8601String(),
                'disclaimer_version' => (string) $transaction->disclaimer_version,
            ],
        ];

        $response = $this->createPayment($payload);
        if ($response->failed()) {
            Log::channel('single')->warning('[billing.renewal] CreatePayment HTTP failed', [
                'subscription_id' => $subscription->id,
                'transaction_id' => $transaction->id,
                'http_status' => $response->status(),
            ]);
            $failure = $this->extractFailureDetails($response->json() ?? [], (string) $response->status(), 'Create payment failed');
            $nextRetryAt = $this->computeNextRetryAt($attemptNo);
            $transaction->update([
                'status' => 'failed',
                'gateway_status' => 'failed',
                'last_error_code' => $failure['code'],
                'last_error_message' => $failure['message'],
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
                'failure_code' => $transaction->last_error_code,
                'failure_reason' => $transaction->last_error_message,
            ];
        }

        $payment = $response->json() ?? [];
        $gatewayStatus = (string) ($payment['status'] ?? '');
        $status = $this->mapGatewayStatusToTransactionStatus($gatewayStatus);
        $failure = $status === 'failed'
            ? $this->extractFailureDetails($payment)
            : ['code' => null, 'message' => null];

        $transaction->update([
            'provider_payment_id' => $payment['id'] ?? null,
            'gateway_status' => $gatewayStatus,
            'status' => $status,
            'redirect_url' => $payment['source']['transaction_url'] ?? null,
            'raw_response' => $this->sanitizeGatewayResponse($payment),
            'last_error_code' => $failure['code'],
            'last_error_message' => $failure['message'],
            'next_retry_at' => $status === 'failed' ? $this->computeNextRetryAt($attemptNo) : null,
            'finalized_at' => in_array($gatewayStatus, self::FINALIZED_STATUSES, true) ? now() : null,
        ]);

        if ($status === 'failed') {
            Log::channel('single')->info('[billing.renewal] Charge failed', [
                'subscription_id' => $subscription->id,
                'transaction_id' => $transaction->id,
                'attempt_no' => $attemptNo,
            ]);
            $subscription->update([
                'status' => $attemptNo >= self::MAX_RENEWAL_ATTEMPTS ? 'expired' : 'past_due',
                'auto_renew' => $attemptNo >= self::MAX_RENEWAL_ATTEMPTS ? false : $subscription->auto_renew,
            ]);
            try {
                app(BillingEmailService::class)->queueRenewalStatus($transaction->fresh(), false);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // عند نجاح الدفع فوراً (بدون انتظار الويب هوك): تحديث الاشتراك + حدث renewed في transaction واحد (لا تحديث بدون حدث)
        if (in_array($gatewayStatus, self::FINAL_SUCCESS_STATUSES, true) && $status === 'paid') {
            Log::channel('single')->info('[billing.renewal] Charge succeeded (sync)', [
                'subscription_id' => $subscription->id,
                'transaction_id' => $transaction->id,
                'provider_payment_id' => $transaction->provider_payment_id,
                'plan_interval' => $plan->interval ?? null,
                'new_end_date' => $periodEnd->toIso8601String(),
            ]);
            DB::transaction(function () use ($subscription, $transaction, $periodStart, $periodEnd, $plan) {
                $subscription->update([
                    'status' => 'active',
                    'start_date' => $periodStart,
                    'end_date' => $periodEnd,
                ]);
                $amountCharged = $transaction->display_amount_minor !== null
                    ? (float) ($transaction->display_amount_minor / 100)
                    : (float) ($subscription->plan?->price ?? $plan->price ?? 0);
                SubscriptionEvent::query()->create([
                    'subscription_id' => $subscription->id,
                    'event_type' => 'renewed',
                    'plan_id' => (int) $subscription->plan_id,
                    'status' => 'active',
                    'start_date' => $periodStart,
                    'end_date' => $periodEnd,
                    'plan_price' => $subscription->plan?->price ?? $subscription->price,
                    'amount_charged' => $amountCharged,
                    'amount_refunded' => 0,
                    'currency' => (string) ($transaction->display_currency ?? 'USD'),
                    'meta' => ['payment_transaction_id' => $transaction->id, 'source' => 'attempt_renewal'],
                ]);
            });
            try {
                $invoiceService = app(InvoiceService::class);
                $billingEmailService = app(BillingEmailService::class);
                $freshTransaction = $transaction->fresh();
                $invoice = $invoiceService->issueFromTransaction($freshTransaction);
                $billingEmailService->queueInvoiceIssued($invoice);
                $billingEmailService->queueRenewalStatus($freshTransaction, true);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return [
            'transaction_id' => $transaction->id,
            'merchant_reference_id' => $transaction->merchant_reference_id,
            'gateway_payment_id' => $transaction->provider_payment_id,
            'gateway_status' => $gatewayStatus,
            'status' => $transaction->status,
            'finalized' => in_array($gatewayStatus, self::FINALIZED_STATUSES, true),
            'verified' => $status === 'paid',
            'failure_code' => $transaction->last_error_code,
            'failure_reason' => $transaction->last_error_message,
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
            $transaction = $transaction->fresh();
        }

        return [
            'merchant_reference_id' => (string) $transaction->merchant_reference_id,
            'verified' => $verified,
            'finalized' => $finalized,
            'gateway_status' => $gatewayStatus,
            'failure_code' => $transaction->last_error_code,
            'failure_reason' => $transaction->last_error_message,
        ];
    }

    public function finalizeByGatewayPaymentId(string $paymentId, string $gatewayStatus, array $rawPayload = []): void
    {
        Log::channel('single')->info('[billing.finalize] Start', [
            'payment_id' => $paymentId,
            'gateway_status' => $gatewayStatus,
        ]);

        DB::transaction(function () use ($paymentId, $gatewayStatus, $rawPayload) {
            /** @var PaymentTransaction|null $transaction */
            $transaction = PaymentTransaction::query()
                ->where('provider', 'moyasar')
                ->where('provider_payment_id', $paymentId)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                $created = $this->createTransactionFromGatewayPayload($paymentId, $gatewayStatus, $rawPayload);
                if (!$created) {
                    Log::channel('single')->warning('[billing.finalize] Could not create transaction from payload', ['payment_id' => $paymentId]);
                    return;
                }
                $transaction = PaymentTransaction::query()
                    ->where('id', $created->id)
                    ->lockForUpdate()
                    ->first();
                if (!$transaction) {
                    return;
                }
            }

            $previousStatus = (string) $transaction->status;
            $nextStatus = $this->mapGatewayStatusToTransactionStatus($gatewayStatus);
            $failure = $nextStatus === 'failed'
                ? $this->extractFailureDetails($rawPayload)
                : ['code' => null, 'message' => null];

            $transaction->update([
                'gateway_status' => $gatewayStatus,
                'status' => $nextStatus,
                'raw_response' => !empty($rawPayload) ? $this->sanitizeGatewayResponse($rawPayload) : $transaction->raw_response,
                'last_error_code' => $failure['code'],
                'last_error_message' => $failure['message'],
                'finalized_at' => in_array($gatewayStatus, self::FINALIZED_STATUSES, true) ? now() : null,
            ]);
            $transaction = $transaction->fresh();

            if (in_array($gatewayStatus, ['refunded', 'partially_refunded'], true)) {
                $this->syncRefundStatusForTransaction($transaction, $gatewayStatus);
                $transaction = $transaction->fresh();
                if (!$transaction) {
                    return;
                }
                $nextStatus = (string) $transaction->status;
            }

            // لا تمديد ولا تفعيل إلا بعد تأكد فعلي: معاملة مدفوعة، finalized_at معيّن، ووجود provider_payment_id
            $confirmedPaid = in_array($nextStatus, self::FINAL_SUCCESS_STATUSES, true)
                && $transaction->finalized_at !== null
                && !empty($transaction->provider_payment_id);

            Log::channel('single')->info('[billing.finalize] Transaction updated', [
                'payment_id' => $paymentId,
                'transaction_id' => $transaction->id,
                'next_status' => $nextStatus,
                'confirmed_paid' => $confirmedPaid,
                'subscription_id' => $transaction->subscription_id,
            ]);

            if ($transaction->subscription_id) {
                $subscription = Subscription::query()
                    ->lockForUpdate()
                    ->find($transaction->subscription_id);

                if ($subscription) {
                    if ($confirmedPaid && $nextStatus === 'paid' && $previousStatus !== 'paid') {
                        $subscription->loadMissing('plan');
                        $periodStart = Carbon::parse($subscription->end_date)->addSecond();
                        $newEndDate = $this->computeNextEndDate(
                            $periodStart->copy(),
                            $subscription->plan?->interval ?? 'monthly'
                        );

                        $subscription->update([
                            'status' => 'active',
                            'start_date' => $periodStart,
                            'end_date' => $newEndDate,
                        ]);

                        Log::channel('single')->info('[billing.finalize] Subscription renewed (webhook)', [
                            'subscription_id' => $subscription->id,
                            'transaction_id' => $transaction->id,
                            'new_end_date' => $newEndDate->toIso8601String(),
                        ]);

                        $alreadyEvent = SubscriptionEvent::query()
                            ->where('subscription_id', $subscription->id)
                            ->where('event_type', 'renewed')
                            ->where('meta->payment_transaction_id', $transaction->id)
                            ->exists();
                        if (!$alreadyEvent) {
                            $amountCharged = $transaction->display_amount_minor !== null
                                ? (float) ($transaction->display_amount_minor / 100)
                                : (float) ($subscription->plan?->price ?? 0);
                            SubscriptionEvent::query()->create([
                                'subscription_id' => $subscription->id,
                                'event_type' => 'renewed',
                                'plan_id' => (int) $subscription->plan_id,
                                'status' => 'active',
                                'start_date' => $periodStart,
                                'end_date' => $newEndDate,
                                'plan_price' => $subscription->plan?->price ?? $subscription->price,
                                'amount_charged' => $amountCharged,
                                'amount_refunded' => 0,
                                'currency' => (string) ($transaction->display_currency ?? 'USD'),
                                'meta' => ['payment_transaction_id' => $transaction->id],
                            ]);
                        }
                    } elseif ($nextStatus === 'failed') {
                        $subscription->update(['status' => 'past_due']);
                    }
                }
            } elseif ($confirmedPaid) {
                Log::channel('single')->info('[billing.finalize] First purchase activation', [
                    'transaction_id' => $transaction->id,
                    'user_id' => $transaction->user_id,
                ]);
                $this->activateSubscriptionFromFirstPurchase($transaction);
            }
        });

        $finalized = PaymentTransaction::query()
            ->where('provider', 'moyasar')
            ->where('provider_payment_id', $paymentId)
            ->first();

        $confirmedPaid = $finalized
            && in_array((string) $finalized->status, self::FINAL_SUCCESS_STATUSES, true)
            && $finalized->finalized_at !== null
            && !empty($finalized->provider_payment_id);

        if ($confirmedPaid) {
            try {
                $invoiceService = app(InvoiceService::class);
                $billingEmailService = app(BillingEmailService::class);
                $invoice = $invoiceService->issueFromTransaction($finalized);
                $billingEmailService->queueInvoiceIssued($invoice);
                Log::channel('single')->info('[billing.finalize] Invoice queued', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'transaction_id' => $finalized->id,
                ]);
                // إشعار تجديد عند نجاح من الويب هوك (إن لم يُسجّل مسبقاً من attemptRenewal)
                if ($finalized->subscription_id && !\App\Models\Billing\BillingEmailNotification::query()
                    ->where('type', 'renewal_success')
                    ->where('payload->payment_transaction_id', $finalized->id)
                    ->exists()) {
                    $billingEmailService->queueRenewalStatus($finalized, true);
                    Log::channel('single')->info('[billing.finalize] Renewal notification queued', ['transaction_id' => $finalized->id]);
                }
            } catch (\Throwable $e) {
                Log::channel('single')->error('[billing.finalize] Invoice/notification failed', [
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
        }

        // إشعار فشل التجديد عند دفعة فاشلة لاشتراك
        if ($finalized && $finalized->subscription_id && (string) $finalized->status === 'failed') {
            Log::channel('single')->info('[billing.finalize] Renewal failed, queuing failure notification', [
                'transaction_id' => $finalized->id,
                'user_id' => $finalized->user_id,
            ]);
            try {
                app(BillingEmailService::class)->queueRenewalStatus($finalized, false);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function processRefundWebhook(array $payload, string $gatewayStatus = 'refunded'): void
    {
        $paymentPayload = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $paymentId = $this->toNullableString($paymentPayload['id'] ?? $paymentPayload['payment_id'] ?? null);
        if (!$paymentId) {
            $paymentId = $this->toNullableString($paymentPayload['payment']['id'] ?? null);
        }

        if (!$paymentId) {
            return;
        }

        DB::transaction(function () use ($paymentId, $paymentPayload, $payload, $gatewayStatus) {
            $transaction = PaymentTransaction::query()
                ->where('provider', 'moyasar')
                ->where('provider_payment_id', $paymentId)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                $transaction = $this->createTransactionFromGatewayPayload($paymentId, $gatewayStatus, $payload);
                if (!$transaction) {
                    return;
                }
            }

            $refundAmountMinor = (int) ($paymentPayload['refund']['amount'] ?? $paymentPayload['amount'] ?? 0);
            $refundStatus = $gatewayStatus === 'failed' ? 'failed' : 'completed';
            $failure = $refundStatus === 'failed'
                ? $this->extractFailureDetails($paymentPayload)
                : ['code' => null, 'message' => null];

            $refundId = $this->toNullableString($paymentPayload['refund']['id'] ?? null)
                ?? $this->toNullableString($paymentPayload['refund_id'] ?? null);
            if (!$refundId) {
                $refundId = 'webhook-' . $paymentId . '-' . now()->timestamp;
            }

            $refund = RefundTransaction::query()
                ->where('provider', 'moyasar')
                ->where('provider_payment_id', $paymentId)
                ->where(function ($query) use ($refundId) {
                    $query->where('provider_refund_id', $refundId)
                        ->orWhereNull('provider_refund_id');
                })
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (!$refund) {
                $refund = RefundTransaction::query()->create([
                    'payment_transaction_id' => (int) $transaction->id,
                    'provider' => 'moyasar',
                    'provider_payment_id' => $paymentId,
                    'provider_refund_id' => $refundId,
                    'amount_minor' => max(0, $refundAmountMinor),
                    'currency' => strtoupper((string) ($paymentPayload['currency'] ?? $transaction->currency)),
                    'status' => $refundStatus,
                    'gateway_status' => $gatewayStatus,
                    'error_code' => $failure['code'],
                    'error_message' => $failure['message'],
                    'raw_response' => $paymentPayload,
                    'requested_at' => now(),
                    'refunded_at' => $refundStatus === 'completed' ? now() : null,
                    'failed_at' => $refundStatus === 'failed' ? now() : null,
                ]);
            } else {
                $refund->update([
                    'payment_transaction_id' => (int) $transaction->id,
                    'provider_refund_id' => $refund->provider_refund_id ?: $refundId,
                    'amount_minor' => $refundAmountMinor > 0 ? $refundAmountMinor : (int) $refund->amount_minor,
                    'currency' => strtoupper((string) ($paymentPayload['currency'] ?? $refund->currency ?? $transaction->currency)),
                    'status' => $refundStatus,
                    'gateway_status' => $gatewayStatus,
                    'error_code' => $failure['code'],
                    'error_message' => $failure['message'],
                    'raw_response' => $paymentPayload,
                    'refunded_at' => $refundStatus === 'completed' ? now() : null,
                    'failed_at' => $refundStatus === 'failed' ? now() : null,
                ]);
            }

            $this->syncRefundStatusForTransaction($transaction, $gatewayStatus);
        });
    }

    protected function activateSubscriptionFromFirstPurchase(PaymentTransaction $transaction): void
    {
        $plan = Plan::query()->find($transaction->plan_id);
        if (!$plan) {
            return;
        }

        $periodStart = now();
        $periodEnd = $this->computeNextEndDate($periodStart->copy(), (string) $plan->interval);

        $subscription = Subscription::query()
            ->where('user_id', $transaction->user_id)
            ->orderByDesc('id')
            ->first();

        $payload = [
            'plan_id' => (int) $plan->id,
            'status' => 'active',
            'start_date' => $periodStart,
            'end_date' => $periodEnd,
            'price' => (string) $plan->price,
            'currency' => strtoupper((string) ($transaction->display_currency ?? 'USD')),
            'auto_renew' => true,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ];

        $isFirstSubscription = false;
        if ($subscription) {
            $subscription->update($payload);
            $subscription = $subscription->fresh();
        } else {
            $isFirstSubscription = true;
            $subscription = Subscription::query()->create([
                'user_id' => (int) $transaction->user_id,
                ...$payload,
            ]);
        }

        // إشعار "اشتراكك أصبح فعالًا" عند أول شراء (معاملة بلا subscription_id) سواء وُجد اشتراك قديم أو تم إنشاء جديد
        $isFirstPurchase = $transaction->subscription_id === null;
        if ($subscription && $isFirstPurchase) {
            BillingNotification::subscriptionActivated($subscription);
        }

        // تسجيل حدث الاشتراك (أول مرة فقط)
        if ($subscription && $isFirstSubscription) {
            $amountCharged = $transaction->display_amount_minor !== null
                ? (float) ($transaction->display_amount_minor / 100)
                : (float) $plan->price;
            SubscriptionEvent::query()->create([
                'subscription_id' => $subscription->id,
                'event_type' => 'created',
                'plan_id' => (int) $plan->id,
                'status' => 'active',
                'start_date' => $periodStart,
                'end_date' => $periodEnd,
                'plan_price' => $plan->price,
                'amount_charged' => $amountCharged,
                'amount_refunded' => 0,
                'currency' => strtoupper((string) ($transaction->display_currency ?? 'USD')),
                'meta' => ['payment_transaction_id' => $transaction->id],
            ]);
        }
    }

    protected function fetchPayment(string $paymentId): array
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
        $secretKey = MoyasarConfig::secretKey();

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
            'partially_refunded' => 'partially_refunded',
            default => 'pending',
        };
    }

    protected function computeRenewalPeriod(Subscription $subscription, string $interval): array
    {
        $periodStart = Carbon::parse($subscription->end_date)->addSecond();
        $periodEnd = $this->computeNextEndDate($periodStart->copy(), $interval);

        return [$periodStart, $periodEnd];
    }

    /**
     * احتساب تاريخ نهاية الفترة من بدايتها + مدة الباقة (شهر / 3 / 4 / 6 أشهر / سنة) — التجديد بنفس مدة الباقة فقط.
     */
    protected function computeNextEndDate(Carbon $periodStart, string $interval): Carbon
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
        $paymentPayload = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        // Keep only non-sensitive fields needed for audit/reconciliation.
        return [
            'id' => $paymentPayload['id'] ?? null,
            'status' => $paymentPayload['status'] ?? null,
            'amount' => $paymentPayload['amount'] ?? null,
            'currency' => $paymentPayload['currency'] ?? null,
            'created_at' => $paymentPayload['created_at'] ?? null,
            'metadata' => $paymentPayload['metadata'] ?? null,
            'source' => [
                'type' => $paymentPayload['source']['type'] ?? null,
                'company' => $paymentPayload['source']['company'] ?? null,
                'number' => $paymentPayload['source']['number'] ?? null,
                'reference_number' => $paymentPayload['source']['reference_number'] ?? null,
                'message' => $paymentPayload['source']['message'] ?? null,
                'response_code' => $paymentPayload['source']['response_code'] ?? null,
            ],
        ];
    }

    /**
     * @return array{code: ?string, message: ?string}
     */
    protected function extractFailureDetails(array $payload, ?string $fallbackCode = null, ?string $fallbackMessage = null): array
    {
        $paymentPayload = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $source = is_array($paymentPayload['source'] ?? null) ? $paymentPayload['source'] : [];

        $code = $this->toNullableString($source['response_code'] ?? null)
            ?? $this->toNullableString($paymentPayload['response_code'] ?? null)
            ?? $this->toNullableString($paymentPayload['error']['code'] ?? null)
            ?? $this->toNullableString($payload['errors']['code'] ?? null)
            ?? $this->toNullableString($fallbackCode);

        $message = $this->toNullableString($source['message'] ?? null)
            ?? $this->toNullableString($paymentPayload['message'] ?? null)
            ?? $this->toNullableString($paymentPayload['error']['message'] ?? null)
            ?? $this->toNullableString($payload['message'] ?? null)
            ?? $this->toNullableString($fallbackMessage);

        return [
            'code' => $code,
            'message' => $message,
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

    protected function createTransactionFromGatewayPayload(string $paymentId, string $gatewayStatus, array $rawPayload): ?PaymentTransaction
    {
        $payment = is_array($rawPayload['data'] ?? null) ? $rawPayload['data'] : $rawPayload;
        $metadata = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
        $userId = isset($metadata['user_id']) ? (int) $metadata['user_id'] : 0;
        if ($userId <= 0) {
            return null;
        }

        $planId = isset($metadata['plan_id']) && is_numeric($metadata['plan_id'])
            ? (int) $metadata['plan_id']
            : null;
        $subscriptionId = isset($metadata['subscription_id']) && is_numeric($metadata['subscription_id'])
            ? (int) $metadata['subscription_id']
            : null;
        $status = $this->mapGatewayStatusToTransactionStatus($gatewayStatus);
        $failure = $status === 'failed'
            ? $this->extractFailureDetails($payment)
            : ['code' => null, 'message' => null];

        $merchantReferenceId = $this->toNullableString($metadata['merchant_reference_id'] ?? null);
        $givenId = $this->toNullableString($payment['given_id'] ?? null);

        $existing = null;
        if ($merchantReferenceId) {
            $existing = PaymentTransaction::query()
                ->where('provider', 'moyasar')
                ->where('merchant_reference_id', $merchantReferenceId)
                ->first();
        }

        if (!$existing && $givenId) {
            $existing = PaymentTransaction::query()
                ->where('provider', 'moyasar')
                ->where('given_id', $givenId)
                ->first();
        }

        $billingPeriodStart = null;
        $billingPeriodEnd = null;
        if ($subscriptionId) {
            $sub = Subscription::query()->with('plan')->find($subscriptionId);
            if ($sub && $sub->end_date) {
                $periodStart = Carbon::parse($sub->end_date)->addSecond();
                $interval = (string) ($sub->plan?->interval ?? 'monthly');
                $billingPeriodStart = $periodStart;
                $billingPeriodEnd = $this->computeNextEndDate($periodStart->copy(), $interval);
            }
        }

        $paymentAmountMinor = max(0, (int) ($payment['amount'] ?? 0));
        $paymentCurrency = strtoupper((string) ($payment['currency'] ?? 'SAR'));

        // Enrich display snapshot from metadata (only for missing/legacy transactions).
        $displayCurrency = $this->toNullableString($metadata['display_currency'] ?? null) ?? 'USD';
        $displayAmountMinor = isset($metadata['display_amount_usd_minor']) && is_numeric($metadata['display_amount_usd_minor'])
            ? (int) $metadata['display_amount_usd_minor']
            : null;
        $exchangeRate = $this->toNullableString($metadata['exchange_rate_usd_to_sar'] ?? null);
        $exchangeAt = $this->toNullableString($metadata['exchange_rate_at'] ?? null);
        $exchangeSource = $this->toNullableString($metadata['exchange_rate_source'] ?? null);
        $disclaimerVersion = $this->toNullableString($metadata['disclaimer_version'] ?? null);

        if ($existing) {
            $existing->update([
                'provider_payment_id' => $paymentId,
                'plan_id' => $planId,
                'subscription_id' => $subscriptionId,
                'amount_minor' => $paymentAmountMinor,
                'currency' => $paymentCurrency,
                'billing_period_start' => $billingPeriodStart,
                'billing_period_end' => $billingPeriodEnd,
                'status' => $status,
                'gateway_status' => $gatewayStatus,
                'raw_response' => $this->sanitizeGatewayResponse($payment),
                'last_error_code' => $failure['code'],
                'last_error_message' => $failure['message'],
                'finalized_at' => in_array($gatewayStatus, self::FINALIZED_STATUSES, true) ? now() : null,
            ]);

            // Preserve existing prepared display snapshot; only fill missing fields.
            $missing = [];
            if (empty($existing->display_currency)) {
                $missing['display_currency'] = $displayCurrency;
            }
            if ($existing->display_amount_minor === null && $displayAmountMinor !== null) {
                $missing['display_amount_minor'] = $displayAmountMinor;
            }
            if ($existing->exchange_rate_usd_to_sar === null && $exchangeRate !== null) {
                $missing['exchange_rate_usd_to_sar'] = $exchangeRate;
            }
            if ($existing->exchange_rate_at === null && $exchangeAt !== null) {
                $missing['exchange_rate_at'] = Carbon::parse($exchangeAt);
            }
            if ($existing->exchange_rate_source === null && $exchangeSource !== null) {
                $missing['exchange_rate_source'] = $exchangeSource;
            }
            if ($existing->disclaimer_version === null && $disclaimerVersion !== null) {
                $missing['disclaimer_version'] = $disclaimerVersion;
            }

            if ($missing !== []) {
                $existing->update($missing);
            }

            return $existing;
        }

        if (!$merchantReferenceId) {
            $merchantReferenceId = (string) Str::uuid();
        }
        if (!$givenId) {
            $givenId = (string) Str::uuid();
        }

        return PaymentTransaction::query()->updateOrCreate(
            [
                'provider' => 'moyasar',
                'provider_payment_id' => $paymentId,
            ],
            [
                'user_id' => $userId,
                'plan_id' => $planId,
                'subscription_id' => $subscriptionId,
                'merchant_reference_id' => $merchantReferenceId,
                'given_id' => $givenId,
                'amount_minor' => $paymentAmountMinor,
                'currency' => $paymentCurrency,
                'display_currency' => $displayCurrency,
                'display_amount_minor' => $displayAmountMinor,
                'exchange_rate_usd_to_sar' => $exchangeRate,
                'exchange_rate_at' => $exchangeAt ? Carbon::parse($exchangeAt) : null,
                'exchange_rate_source' => $exchangeSource,
                'disclaimer_version' => $disclaimerVersion,
                'billing_period_start' => $billingPeriodStart,
                'billing_period_end' => $billingPeriodEnd,
                'status' => $status,
                'gateway_status' => $gatewayStatus,
                'raw_response' => $this->sanitizeGatewayResponse($payment),
                'last_error_code' => $failure['code'],
                'last_error_message' => $failure['message'],
                'finalized_at' => in_array($gatewayStatus, self::FINALIZED_STATUSES, true) ? now() : null,
            ]
        );
    }

    protected function syncRefundStatusForTransaction(PaymentTransaction $transaction, ?string $fallbackGatewayStatus = null): void
    {
        $totalCompletedRefundMinor = (int) RefundTransaction::query()
            ->where('payment_transaction_id', $transaction->id)
            ->where('status', 'completed')
            ->sum('amount_minor');

        if ($totalCompletedRefundMinor <= 0) {
            return;
        }

        $amountMinor = (int) $transaction->amount_minor;
        $isFullyRefunded = $amountMinor > 0 && $totalCompletedRefundMinor >= $amountMinor;
        $status = $isFullyRefunded ? 'refunded' : 'partially_refunded';
        $gatewayStatus = $fallbackGatewayStatus ?: $status;

        $raw = is_array($transaction->raw_response) ? $transaction->raw_response : [];
        $raw['refund_summary'] = [
            'total_refunded_minor' => $totalCompletedRefundMinor,
            'is_fully_refunded' => $isFullyRefunded,
            'updated_at' => now()->toIso8601String(),
        ];

        $transaction->update([
            'status' => $status,
            'gateway_status' => $gatewayStatus,
            'raw_response' => $raw,
            'finalized_at' => now(),
        ]);
    }

    protected function hasPurchaseBlockingSubscription(int $userId): bool
    {
        $graceMinutes = (int) config('services.billing.renewal_grace_period_minutes', 15);
        $graceCutoff = now()->subMinutes($graceMinutes);

        return Subscription::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['active', 'past_due'])
            ->where(function ($query) use ($graceCutoff) {
                $query->where('end_date', '>=', now())
                    ->orWhere(function ($q) use ($graceCutoff) {
                        $q->where('auto_renew', true)
                            ->where('end_date', '>=', $graceCutoff);
                    });
            })
            ->exists();
    }
}

