<?php

namespace App\Services\AiNegotiator\Llm\Contracts;

class LlmProviderException extends \RuntimeException
{
    public static function authFailed(): self
    {
        return new self('LLM provider authentication failed.', 401);
    }

    public static function rateLimited(): self
    {
        return new self('LLM provider rate limit exceeded.', 429);
    }

    public static function badRequest(string $message): self
    {
        return new self('LLM provider bad request: ' . $message, 400);
    }

    public static function serverError(string $message): self
    {
        return new self('LLM provider server error: ' . $message, 500);
    }

    public static function timeout(): self
    {
        return new self('LLM provider request timed out.', 408);
    }
}
