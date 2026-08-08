<?php

namespace App\Http\Services\AiNegotiator\Simulation;

use App\Http\Services\AiNegotiator\MessageService;
use App\Models\AiNegotiator\AiNegotiatorMessage;
use App\Models\AiNegotiator\AiNegotiatorSession;
use App\Models\System\Setting;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderInterface;
use App\Services\AiNegotiator\Prompts\PromptTemplateService;

class OpponentCoordinator
{
    public function __construct(
        protected MessageService $messages,
        protected PromptTemplateService $prompts,
    ) {}

    /**
     * @return array{user_message: AiNegotiatorMessage, assistant_message: AiNegotiatorMessage, reached_message_limit: bool}
     */
    public function handleUserMessage(
        AiNegotiatorSession $session,
        string $userMessage,
        LlmProviderInterface $llm
    ): array {
        $userMessageModel = $this->messages->persistUserMessage($session, $userMessage, 'simulating');

        $systemPrompt = $this->prompts->buildOpponentPrompt(
            is_array($session->intake_data) ? $session->intake_data : [],
            is_array($session->opponent_persona) ? $session->opponent_persona : [],
            (string) $session->difficulty,
            (string) $session->training_mode
        );

        $history = $this->messages->getMessagesForLlmContext($session, 'simulating');

        $response = $llm->chat($systemPrompt, $history, ['max_tokens' => 1000]);

        $assistantMessageModel = $this->messages->persistAssistantMessage(
            $session,
            $response->content,
            'simulating',
            $response->outputTokens
        );

        $limit = $this->maxMessagesPerSession();
        $count = $this->messages->countMessagesInSession($session);

        return [
            'user_message' => $userMessageModel,
            'assistant_message' => $assistantMessageModel,
            'reached_message_limit' => $count >= $limit,
        ];
    }

    private function maxMessagesPerSession(): int
    {
        try {
            $setting = Setting::query()->where('key_name', 'ai_negotiator.max_messages_per_session')->first();
            $value = $setting?->value;
            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        } catch (\Throwable) {
            // fall through
        }

        return 40;
    }
}
