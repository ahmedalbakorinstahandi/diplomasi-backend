<?php

namespace App\Http\Services\AiNegotiator;

use App\Models\AiNegotiator\AiNegotiatorMessage;
use App\Models\AiNegotiator\AiNegotiatorSession;

class MessageService
{
    public function persistUserMessage(
        AiNegotiatorSession $session,
        string $content,
        string $stateAtTime
    ): AiNegotiatorMessage {
        return AiNegotiatorMessage::create([
            'ai_negotiator_session_id' => $session->id,
            'role' => 'user',
            'type' => 'text',
            'content' => $content,
            'tokens_used' => null,
            'state_at_time' => $stateAtTime,
            'order_index' => $this->nextOrderIndex($session),
        ]);
    }

    public function persistAssistantMessage(
        AiNegotiatorSession $session,
        string $content,
        string $stateAtTime,
        ?int $tokensUsed = null
    ): AiNegotiatorMessage {
        return AiNegotiatorMessage::create([
            'ai_negotiator_session_id' => $session->id,
            'role' => 'assistant',
            'type' => 'text',
            'content' => $content,
            'tokens_used' => $tokensUsed,
            'state_at_time' => $stateAtTime,
            'order_index' => $this->nextOrderIndex($session),
        ]);
    }

    /**
     * @return list<array{role: string, content: string, state_at_time: string, order_index: int}>
     */
    public function getSessionTranscript(AiNegotiatorSession $session, ?string $stateFilter = null): array
    {
        $query = AiNegotiatorMessage::query()
            ->where('ai_negotiator_session_id', $session->id)
            ->orderBy('order_index');

        if ($stateFilter !== null) {
            $query->where('state_at_time', $stateFilter);
        }

        return $query->get()->map(static function (AiNegotiatorMessage $message): array {
            return [
                'role' => (string) $message->role,
                'content' => (string) $message->content,
                'state_at_time' => (string) $message->state_at_time,
                'order_index' => (int) $message->order_index,
            ];
        })->all();
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    public function getMessagesForLlmContext(AiNegotiatorSession $session, string $stateFilter): array
    {
        return AiNegotiatorMessage::query()
            ->where('ai_negotiator_session_id', $session->id)
            ->where('state_at_time', $stateFilter)
            ->orderBy('order_index')
            ->get()
            ->map(static function (AiNegotiatorMessage $message): array {
                return [
                    'role' => (string) $message->role,
                    'content' => (string) $message->content,
                ];
            })
            ->all();
    }

    public function countMessagesInSession(AiNegotiatorSession $session): int
    {
        return (int) AiNegotiatorMessage::query()
            ->where('ai_negotiator_session_id', $session->id)
            ->count();
    }

    private function nextOrderIndex(AiNegotiatorSession $session): int
    {
        $max = AiNegotiatorMessage::query()
            ->where('ai_negotiator_session_id', $session->id)
            ->max('order_index');

        return $max === null ? 1 : ((int) $max + 1);
    }
}
