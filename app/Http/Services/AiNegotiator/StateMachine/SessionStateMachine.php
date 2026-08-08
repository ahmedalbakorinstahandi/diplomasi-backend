<?php

namespace App\Http\Services\AiNegotiator\StateMachine;

use App\Models\AiNegotiator\AiNegotiatorSession;
use App\Models\AiNegotiator\AiNegotiatorSessionEvent;
use Illuminate\Support\Facades\DB;

class SessionStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'intake' => ['simulating', 'abandoned'],
        'simulating' => ['evaluating', 'abandoned'],
        'evaluating' => ['completed', 'abandoned'],
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public function transition(AiNegotiatorSession $session, string $toState, array $context = []): void
    {
        $fromState = (string) $session->session_state;

        if ($this->isTerminal($fromState)) {
            throw SessionStateException::sessionAlreadyTerminal($fromState);
        }

        if (!$this->canTransition($fromState, $toState)) {
            throw SessionStateException::invalidTransition($fromState, $toState);
        }

        DB::transaction(function () use ($session, $fromState, $toState, $context) {
            $session->session_state = $toState;

            if ($toState === 'simulating' && $session->simulating_started_at === null) {
                $session->simulating_started_at = now();
            }

            if ($toState === 'completed') {
                $session->completed_at = now();
            }

            if ($toState === 'abandoned') {
                $session->abandoned_at = now();
            }

            $session->save();

            $this->writeEvent($session, $fromState, $toState, $context);
        });
    }

    /**
     * Audit-only bootstrap for a newly created intake session (from_state = null).
     *
     * @param  array<string, mixed>  $context
     */
    public function bootstrapIntake(AiNegotiatorSession $session, array $context = []): void
    {
        if ($session->session_state !== 'intake') {
            throw SessionStateException::invalidTransition((string) $session->session_state, 'intake');
        }

        DB::transaction(function () use ($session, $context) {
            $this->writeEvent($session, null, 'intake', $context);
        });
    }

    public function isTerminal(string $state): bool
    {
        return in_array($state, ['completed', 'abandoned'], true);
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED[$from] ?? [], true);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function writeEvent(
        AiNegotiatorSession $session,
        ?string $fromState,
        string $toState,
        array $context
    ): void {
        AiNegotiatorSessionEvent::create([
            'ai_negotiator_session_id' => $session->id,
            'from_state' => $fromState,
            'to_state' => $toState,
            'context' => $context === [] ? null : $context,
            'created_at' => now(),
        ]);
    }
}
