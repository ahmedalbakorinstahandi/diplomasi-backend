<?php

namespace App\Http\Services\AiNegotiator\Credits;

use App\Models\System\Setting;
use App\Models\Users\User;

class CreditPolicy
{
    public function accessMode(): string
    {
        $value = $this->settingValue('ai_negotiator.access_mode');

        return is_string($value) && $value !== '' ? $value : 'credits_based';
    }

    public function getMonthlyAllotment(User $user): int
    {
        $mode = $this->accessMode();

        if ($mode === 'free_unlimited') {
            return 9999;
        }

        $paid = (int) ($this->settingValue('ai_negotiator.paid_credits_monthly') ?? 30);
        $free = (int) ($this->settingValue('ai_negotiator.free_credits_monthly') ?? 3);
        $subscribed = $user->hasActiveSubscription();

        if ($mode === 'paid_only') {
            return $subscribed ? $paid : 0;
        }

        // credits_based (default)
        return $subscribed ? $paid : $free;
    }

    private function settingValue(string $key): mixed
    {
        try {
            $setting = Setting::query()->where('key_name', $key)->first();

            return $setting?->value;
        } catch (\Throwable) {
            return null;
        }
    }
}
