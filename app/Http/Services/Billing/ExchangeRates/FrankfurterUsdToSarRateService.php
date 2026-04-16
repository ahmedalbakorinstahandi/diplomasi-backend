<?php

namespace App\Http\Services\Billing\ExchangeRates;

use App\Services\MessageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FrankfurterUsdToSarRateService
{
    private const CURRENCY_API_KEY = 'cur_live_qiCKG6SMPE0t20riQsk64fdty2GcilD8d6CFcBqt';
    private const BASE_CURRENCY = 'USD';
    private const TARGET_CURRENCY = 'SAR';

    // Free plan updates daily, so one cached value per day is enough.
    private const CACHE_FRESH_TTL_SECONDS = 86400; // 24 hours
    private const CACHE_LAST_SUCCESS_TTL_SECONDS = 2592000; // 30 days safety fallback

    private const CACHE_KEY_FRESH = 'fx:usd_to_sar:fresh';
    private const CACHE_KEY_LAST_SUCCESS = 'fx:usd_to_sar:last_success';

    /**
     * @return array{
     *   base:string,
     *   target:string,
     *   rate:string,
     *   source:string,
     *   fetched_at:string
     * }
     */
    public function getRate(): array
    {
        $fresh = Cache::get(self::CACHE_KEY_FRESH);
        if (is_array($fresh) && isset($fresh['rate']) && is_string($fresh['rate']) && $fresh['rate'] !== '') {
            return $fresh;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(8)
                ->connectTimeout(4)
                ->get('https://api.currencyapi.com/v3/latest', [
                    'apikey' => self::CURRENCY_API_KEY,
                    'currencies' => self::TARGET_CURRENCY,
                    'base_currency' => self::BASE_CURRENCY,
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('currencyapi request failed');
            }

            $json = $response->json();
            if (!is_array($json)) {
                throw new \RuntimeException('currencyapi invalid json');
            }

            $rate = $json['data'][self::TARGET_CURRENCY]['value'] ?? null;
            if ($rate === null) {
                $rate = $json['SAR'] ?? null;
            }

            $rateStr = is_numeric($rate) ? (string) $rate : '';
            $rateStr = trim($rateStr);
            if ($rateStr === '') {
                throw new \RuntimeException('currencyapi missing SAR rate');
            }

            $fetchedAt = now()->toIso8601String();
            if (!empty($json['meta']['last_updated_at'])) {
                $fetchedAt = Carbon::parse((string) $json['meta']['last_updated_at'])->toIso8601String();
            }

            $result = [
                'base' => self::BASE_CURRENCY,
                'target' => self::TARGET_CURRENCY,
                'rate' => $rateStr,
                'source' => 'currencyapi',
                'fetched_at' => $fetchedAt,
            ];

            Cache::put(self::CACHE_KEY_FRESH, $result, self::CACHE_FRESH_TTL_SECONDS);
            Cache::put(self::CACHE_KEY_LAST_SUCCESS, $result, self::CACHE_LAST_SUCCESS_TTL_SECONDS);

            return $result;
        } catch (\Throwable $e) {
            $last = Cache::get(self::CACHE_KEY_LAST_SUCCESS);
            if (is_array($last) && isset($last['rate']) && is_string($last['rate']) && $last['rate'] !== '') {
                return $last;
            }

            MessageService::abort(503, 'تعذر الحصول على سعر الصرف USD->SAR حالياً');
        }
    }
}

