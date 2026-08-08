<?php

namespace App\Services\AiNegotiator\Llm\Contracts;

class LlmResponse
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $content,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly string $model,
        public readonly string $stopReason,
        public readonly array $raw = [],
    ) {}
}
