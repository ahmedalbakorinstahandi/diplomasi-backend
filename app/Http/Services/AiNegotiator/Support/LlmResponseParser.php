<?php

namespace App\Http\Services\AiNegotiator\Support;

use JsonException;

class LlmResponseParser
{
    public function stripMarkdownFences(string $content): string
    {
        $trimmed = trim($content);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $trimmed, $matches) === 1) {
            return trim($matches[1]);
        }

        return $trimmed;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function parseJson(string $content): array
    {
        $stripped = $this->stripMarkdownFences($content);
        $decoded = json_decode($stripped, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new JsonException('Decoded JSON is not an object/array.');
        }

        return $decoded;
    }
}
