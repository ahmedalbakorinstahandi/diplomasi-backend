<?php

namespace App\Http\Services\AiNegotiator\Simulation;

use App\Http\Services\AiNegotiator\MessageService;
use App\Http\Services\AiNegotiator\Support\IntakeCompletionDetector;
use App\Models\AiNegotiator\AiNegotiatorMessage;
use App\Models\AiNegotiator\AiNegotiatorSession;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderInterface;
use App\Services\AiNegotiator\Prompts\PromptTemplateService;

class IntakeCoordinator
{
    public function __construct(
        protected MessageService $messages,
        protected PromptTemplateService $prompts,
        protected IntakeCompletionDetector $completionDetector,
    ) {}

    /**
     * @return array{user_message: AiNegotiatorMessage, assistant_message: AiNegotiatorMessage, intake_complete: bool, raw_assistant_response: string}
     */
    public function handleUserMessage(
        AiNegotiatorSession $session,
        string $userMessage,
        LlmProviderInterface $llm
    ): array {
        $userMessageModel = $this->messages->persistUserMessage($session, $userMessage, 'intake');

        $systemPrompt = $this->prompts->buildIntakePrompt();
        $history = $this->messages->getMessagesForLlmContext($session, 'intake');

        $response = $llm->chat($systemPrompt, $history, ['max_tokens' => 800]);
        $raw = $response->content;
        $complete = $this->completionDetector->isComplete($raw);
        $visible = $this->completionDetector->stripCompletionToken($raw);

        $assistantMessageModel = $this->messages->persistAssistantMessage(
            $session,
            $visible,
            'intake',
            $response->outputTokens
        );

        return [
            'user_message' => $userMessageModel,
            'assistant_message' => $assistantMessageModel,
            'intake_complete' => $complete,
            'raw_assistant_response' => $raw,
        ];
    }
}
