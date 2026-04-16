<?php

namespace App\Http\Services\Billing\ExchangeRates;

use App\Services\MessageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FrankfurterUsdToSarRateService
{
    private const EXCHANGE_RATE_API_KEY = 'b9ad4e78dc72c0a726e2029d';

    private const CACHE_FRESH_TTL_SECONDS = 3600; // 1 hour
    private const CACHE_LAST_SUCCESS_TTL_SECONDS = 86400; // 24 hours

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
                ->get('https://v6.exchangerate-api.com/v6/' . self::EXCHANGE_RATE_API_KEY . '/latest/USD');

            if ($response->failed()) {
                throw new \RuntimeException('ExchangeRate API request failed');
            }

            $json = $response->json();
            if (!is_array($json)) {
                throw new \RuntimeException('ExchangeRate API invalid json');
            }

            $resultStatus = strtolower(trim((string) ($json['result'] ?? '')));
            if ($resultStatus !== 'success') {
                throw new \RuntimeException('ExchangeRate API non-success result');
            }

            $rate = $json['conversion_rates']['SAR'] ?? null;
            if ($rate === null) {
                $rate = $json['SAR'] ?? null;
            }

            $rateStr = is_numeric($rate) ? (string) $rate : '';
            $rateStr = trim($rateStr);
            if ($rateStr === '') {
                throw new \RuntimeException('ExchangeRate API missing SAR rate');
            }

            $fetchedAt = now()->toIso8601String();
            if (!empty($json['time_last_update_utc'])) {
                $fetchedAt = Carbon::parse((string) $json['time_last_update_utc'])->toIso8601String();
            } elseif (!empty($json['time_last_update_unix'])) {
                $fetchedAt = Carbon::createFromTimestamp((int) $json['time_last_update_unix'])->toIso8601String();
            }

            $result = [
                'base' => 'USD',
                'target' => 'SAR',
                'rate' => $rateStr,
                'source' => 'exchangerate-api',
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

