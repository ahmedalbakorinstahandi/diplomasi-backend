<?php

namespace App\Services\AiNegotiator\Llm\Providers;

use App\Models\System\Setting;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderException;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderInterface;
use App\Services\AiNegotiator\Llm\Contracts\LlmResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ClaudeLlmProvider implements LlmProviderInterface
{
    public function name(): string
    {
        return 'claude';
    }

    /**
     * {@inheritdoc}
     */
    public function chat(string $systemPrompt, array $messages, array $options = []): LlmResponse
    {
        $apiKey = (string) config('services.ai_negotiator.claude.api_key', '');
        $baseUrl = rtrim((string) config('services.ai_negotiator.claude.base_url', 'https://api.anthropic.com/v1'), '/');

        if ($apiKey === '') {
            throw LlmProviderException::authFailed();
        }

        $model = $options['model']
            ?? $this->resolveDefaultModel()
            ?? 'claude-sonnet-4-6';

        $payload = [
            'model' => $model,
            'max_tokens' => (int) ($options['max_tokens'] ?? 2048),
            'system' => $systemPrompt,
            'messages' => array_values(array_map(static function (array $message): array {
                return [
                    'role' => $message['role'],
                    'content' => $message['content'],
                ];
            }, $messages)),
        ];

        if (array_key_exists('temperature', $options)) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        if (!empty($options['stop_sequences']) && is_array($options['stop_sequences'])) {
            $payload['stop_sequences'] = array_values($options['stop_sequences']);
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->connectTimeout(10)
                ->timeout(60)
                ->post("{$baseUrl}/messages", $payload);
        } catch (ConnectionException $e) {
            throw LlmProviderException::timeout();
        }

        $status = $response->status();
        $body = $response->json() ?? $response->body();
        $bodyMessage = is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE) : (string) $body;

        if ($status === 401) {
            throw LlmProviderException::authFailed();
        }

        if ($status === 429) {
            throw LlmProviderException::rateLimited();
        }

        if ($status >= 400 && $status < 500) {
            throw LlmProviderException::badRequest($bodyMessage);
        }

        if ($status >= 500) {
            throw LlmProviderException::serverError($bodyMessage);
        }

        if (!$response->successful() || !is_array($body)) {
            throw LlmProviderException::serverError($bodyMessage);
        }

        return $this->toLlmResponse($body);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function toLlmResponse(array $body): LlmResponse
    {
        $content = '';
        $blocks = $body['content'] ?? [];
        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                if (is_array($block) && ($block['type'] ?? null) === 'text' && isset($block['text'])) {
                    $content = (string) $block['text'];
                    break;
                }
            }
        }

        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];

        return new LlmResponse(
            content: $content,
            inputTokens: (int) ($usage['input_tokens'] ?? 0),
            outputTokens: (int) ($usage['output_tokens'] ?? 0),
            model: (string) ($body['model'] ?? ''),
            stopReason: (string) ($body['stop_reason'] ?? ''),
            raw: $body,
        );
    }

    protected function resolveDefaultModel(): ?string
    {
        try {
            $setting = Setting::query()->where('key_name', 'ai_negotiator.llm_model')->first();
            if (!$setting) {
                return null;
            }

            $value = $setting->value;

            return is_string($value) && $value !== '' ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }
}