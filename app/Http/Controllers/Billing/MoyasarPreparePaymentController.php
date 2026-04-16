<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\PrepareMoyasarPaymentRequest;
use App\Http\Services\Billing\UsdSarConversionService;
use App\Models\Billing\PaymentTransaction;
use App\Models\Billing\Plan;
use App\Support\MoyasarConfig;
use App\Services\ResponseService;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class MoyasarPreparePaymentController extends Controller
{
    public function __construct(
        protected UsdSarConversionService $conversionService
    ) {}

    public function prepare(PrepareMoyasarPaymentRequest $request)
    {
        $type = (string) $request->validated()['type'];
        $userId = (int) $request->user()->id;
        $expiresAt = now()->addMinutes(10);

        $merchantReferenceId = (string) Str::uuid();
        $givenId = (string) Str::uuid();

        $disclaimerVersion = 'sar_only_v1';
        $disclaimerTextAr = 'يتم تنفيذ الدفع بالريال السعودي (SAR). قد يختلف المبلغ النهائي في كشف البنك قليلاً حسب سعر الصرف المعتمد لدى البنك وأي رسوم تحويل أو معاملات عملة أجنبية.';

        $displayUsdAmountMinor = 0;
        $planId = null;

        if ($type === 'plan_purchase') {
            $planId = (int) $request->validated()['plan_id'];
            $plan = Plan::query()->find($planId);
            if (!$plan) {
                return ResponseService::response([
                    'data' => null,
                    'message' => 'messages.plan.not_found',
                ], 404);
            }

            $displayUsdAmountMinor = (int) round(((float) $plan->price) * 100, 0, PHP_ROUND_HALF_UP);
        } elseif ($type === 'card_verification') {
            // Keep existing verification amount reference: 1.00 USD (display).
            $displayUsdAmountMinor = 100;
        } else {
            return ResponseService::response([
                'data' => null,
                'message' => 'Invalid prepare type',
            ], 422);
        }

        $conversion = $this->conversionService->convertUsdMinorToSarMinor($displayUsdAmountMinor);
        $paymentSarAmountMinor = (int) $conversion['payment_sar_amount_minor'];

        $transaction = PaymentTransaction::query()->create([
            'user_id' => $userId,
            'plan_id' => $planId,
            'subscription_id' => null,
            'merchant_reference_id' => $merchantReferenceId,
            'given_id' => $givenId,
            'provider' => 'moyasar',
            'amount_minor' => $paymentSarAmountMinor, // SAR minor
            'currency' => 'SAR',
            'display_currency' => 'USD',
            'display_amount_minor' => $displayUsdAmountMinor,
            'exchange_rate_usd_to_sar' => (string) $conversion['rate'],
            'exchange_rate_at' => Carbon::parse((string) $conversion['rate_at']),
            'exchange_rate_source' => (string) $conversion['source'],
            'disclaimer_version' => $disclaimerVersion,
            'expires_at' => $expiresAt,
            'status' => 'prepared',
            'raw_response' => null,
        ]);

        return ResponseService::response([
            'data' => [
                'prepared_transaction_id' => $transaction->id,
                'merchant_reference_id' => (string) $transaction->merchant_reference_id,
                'given_id' => (string) $transaction->given_id,
                'payment_currency' => 'SAR',
                'payment_amount_sar_minor' => (int) $transaction->amount_minor,
                'display_currency' => 'USD',
                'display_amount_usd_minor' => (int) $transaction->display_amount_minor,
                'exchange_rate_usd_to_sar' => (string) $transaction->exchange_rate_usd_to_sar,
                'exchange_rate_at' => $transaction->exchange_rate_at,
                'expires_at' => $transaction->expires_at,
                'disclaimer_version' => (string) $transaction->disclaimer_version,
                'disclaimer_text_ar' => $disclaimerTextAr,
                'payment_mode' => MoyasarConfig::activeMode(),
                'publishable_key' => MoyasarConfig::publicKey(),
            ],
        ], 200);
    }
}

