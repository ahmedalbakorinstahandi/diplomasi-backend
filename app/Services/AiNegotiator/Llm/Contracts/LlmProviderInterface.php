<?php

namespace App\Services\AiNegotiator\Llm\Contracts;

interface LlmProviderInterface
{
    /**
     * Send a chat completion request.
     *
     * @param  string  $systemPrompt  The system prompt (role instructions, guardrails, context).
     * @param  array<int, array{role: string, content: string}>  $messages  Array of ['role' => 'user'|'assistant', 'content' => '...'].
     * @param  array<string, mixed>  $options  Provider options: model, max_tokens, temperature, stop_sequences.
     *
     * @throws LlmProviderException
     */
    public function chat(string $systemPrompt, array $messages, array $options = []): LlmResponse;

    /**
     * Provider identifier (for logging + settings match).
     */
    public function name(): string;
}
