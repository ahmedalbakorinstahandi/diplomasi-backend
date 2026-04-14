<?php

namespace App\Support;

use App\Models\System\Setting;

/**
 * Resolves Moyasar keys from env by active mode (DB setting billing.moyasar.mode or MOYASAR_MODE).
 */
class MoyasarConfig
{
    public const MODE_TEST = 'test';

    public const MODE_LIVE = 'live';

    public static function activeMode(): string
    {
        $setting = Setting::query()->where('key_name', 'billing.moyasar.mode')->first();
        if ($setting) {
            $raw = strtolower(trim((string) ($setting->getAttributes()['value'] ?? '')));
            if ($raw === 'production') {
                $raw = self::MODE_LIVE;
            }
            if (in_array($raw, [self::MODE_TEST, self::MODE_LIVE], true)) {
                return $raw;
            }
        }

        $env = strtolower(trim((string) env('MOYASAR_MODE', self::MODE_LIVE)));
        if ($env === 'production') {
            $env = self::MODE_LIVE;
        }

        return in_array($env, [self::MODE_TEST, self::MODE_LIVE], true)
            ? $env
            : self::MODE_LIVE;
    }

    public static function publicKey(): string
    {
        return self::publicKeyForMode(self::activeMode());
    }

    public static function secretKey(): string
    {
        return self::secretKeyForMode(self::activeMode());
    }

    public static function publicKeyForMode(string $mode): string
    {
        $mode = $mode === self::MODE_LIVE ? self::MODE_LIVE : self::MODE_TEST;
        if ($mode === self::MODE_TEST) {
            $k = trim((string) env('MOYASAR_TEST_PUBLIC_KEY'));

            return $k !== '' ? $k : trim((string) env('MOYASAR_PUBLIC_KEY'));
        }

        $k = trim((string) env('MOYASAR_LIVE_PUBLIC_KEY'));

        return $k !== '' ? $k : trim((string) env('MOYASAR_PUBLIC_KEY'));
    }

    public static function secretKeyForMode(string $mode): string
    {
        $mode = $mode === self::MODE_LIVE ? self::MODE_LIVE : self::MODE_TEST;
        if ($mode === self::MODE_TEST) {
            $k = trim((string) env('MOYASAR_TEST_SECRET_KEY'));

            return $k !== '' ? $k : trim((string) env('MOYASAR_SECRET_KEY'));
        }

        $k = trim((string) env('MOYASAR_LIVE_SECRET_KEY'));

        return $k !== '' ? $k : trim((string) env('MOYASAR_SECRET_KEY'));
    }

    /**
     * All configured webhook secrets (test + live) so webhooks still verify after a mode switch
     * or when events reference either account.
     *
     * @return list<string>
     */
    public static function webhookSecretsForVerification(): array
    {
        $out = [];
        $test = trim((string) env('MOYASAR_TEST_WEBHOOK_SECRET_TOKEN'));
        if ($test === '') {
            $test = trim((string) env('MOYASAR_WEBHOOK_SECRET_TOKEN'));
        }
        $live = trim((string) env('MOYASAR_LIVE_WEBHOOK_SECRET_TOKEN'));
        if ($live === '') {
            $live = trim((string) env('MOYASAR_WEBHOOK_SECRET_TOKEN'));
        }
        foreach ([$test, $live] as $s) {
            if ($s !== '' && ! in_array($s, $out, true)) {
                $out[] = $s;
            }
        }

        return $out;
    }
}
