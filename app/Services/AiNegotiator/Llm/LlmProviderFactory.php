<?php

namespace App\Services\AiNegotiator\Llm;

use App\Models\System\Setting;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderException;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderInterface;
use App\Services\AiNegotiator\Llm\Providers\ClaudeLlmProvider;
use Throwable;

class LlmProviderFactory
{
    public static function make(?string $providerName = null): LlmProviderInterface
    {
        $name = $providerName;

        if ($name === null || $name === '') {
            $name = self::resolveProviderNameFromSettings() ?? 'claude';
        }

        $name = strtolower(trim($name));

        return match ($name) {
            'claude' => app(ClaudeLlmProvider::class),
            default => throw LlmProviderException::badRequest("Unknown provider: {$name}"),
        };
    }

    protected static function resolveProviderNameFromSettings(): ?string
    {
        try {
            $setting = Setting::query()->where('key_name', 'ai_negotiator.llm_provider')->first();
            $value = $setting?->value;

            return is_string($value) && $value !== '' ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }
}
