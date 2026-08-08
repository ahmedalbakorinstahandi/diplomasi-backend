<?php

namespace App\Http\Services\AiNegotiator\Support;

class IntakeCompletionDetector
{
    public const TOKEN = '<INTAKE_COMPLETE>';

    public function isComplete(string $assistantMessage): bool
    {
        return stripos($assistantMessage, self::TOKEN) !== false;
    }

    public function stripCompletionToken(string $message): string
    {
        $cleaned = preg_replace('/\s*' . preg_quote(self::TOKEN, '/') . '\s*/i', "\n", $message) ?? $message;

        return trim(preg_replace("/\n{3,}/", "\n\n", $cleaned) ?? $cleaned);
    }
}
