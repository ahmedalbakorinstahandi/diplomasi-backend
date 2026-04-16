<?php

namespace App\Http\Services\Billing;

use App\Http\Services\Billing\ExchangeRates\FrankfurterUsdToSarRateService;

class UsdSarConversionService
{
    public function __construct(
        protected FrankfurterUsdToSarRateService $rateService
    ) {}

    /**
     * @return array{
     *   display_usd_amount_minor:int,
     *   payment_sar_amount_minor:int,
     *   rate:string,
     *   rate_at:string,
     *   source:string
     * }
     */
    public function convertUsdMinorToSarMinor(int $displayUsdAmountMinor): array
    {
        $displayUsdAmountMinor = max(0, (int) $displayUsdAmountMinor);
        if ($displayUsdAmountMinor === 0) {
            return [
                'display_usd_amount_minor' => 0,
                'payment_sar_amount_minor' => 0,
                'rate' => '0',
                'rate_at' => now()->toIso8601String(),
                'source' => 'none',
            ];
        }

        $displayUsdMajor = $displayUsdAmountMinor / 100;
        $rateResult = $this->rateService->getRate();
        $rate = (float) ($rateResult['rate'] ?? 0);

        // Round SAR to 2 decimals before converting to minor units (halalah precision).
        $sarMajor = $displayUsdMajor * $rate;
        $sarMajorRounded = round($sarMajor, 2, PHP_ROUND_HALF_UP);
        $paymentSarMinor = (int) round($sarMajorRounded * 100, 0, PHP_ROUND_HALF_UP);

        return [
            'display_usd_amount_minor' => (int) $displayUsdAmountMinor,
            'payment_sar_amount_minor' => max(0, $paymentSarMinor),
            'rate' => (string) ($rateResult['rate'] ?? '0'),
            'rate_at' => (string) ($rateResult['fetched_at'] ?? now()->toIso8601String()),
            'source' => (string) ($rateResult['source'] ?? 'frankfurter'),
        ];
    }
}

