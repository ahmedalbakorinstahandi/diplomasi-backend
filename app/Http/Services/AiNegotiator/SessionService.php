<?php

namespace App\Http\Services\AiNegotiator;

use App\Http\Services\AiNegotiator\Credits\CreditService;
use App\Http\Services\AiNegotiator\Simulation\EvaluationCoordinator;
use App\Http\Services\AiNegotiator\Simulation\IntakeCoordinator;
use App\Http\Services\AiNegotiator\Simulation\OpponentCoordinator;
use App\Http\Services\AiNegotiator\Simulation\ScenarioBuilder;
use App\Http\Services\AiNegotiator\StateMachine\SessionStateException;
use App\Http\Services\AiNegotiator\StateMachine\SessionStateMachine;
use App\Models\AiNegotiator\AiNegotiatorEvaluation;
use App\Models\AiNegotiator\AiNegotiatorMessage;
use App\Models\AiNegotiator\AiNegotiatorSession;
use App\Models\Users\User;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SessionService
{
    public function __construct(
        protected SessionStateMachine $stateMachine,
        protected CreditService $credits,
        protected MessageService $messages,
        protected IntakeCoordinator $intakeCoordinator,
        protected OpponentCoordinator $opponentCoordinator,
        protected ScenarioBuilder $scenarioBuilder,
        protected EvaluationCoordinator $evaluationCoordinator,
        protected LlmProviderInterface $llm,
    ) {}

    public function startSession(
        User $user,
        string $sessionType = 'practice',
        string $difficulty = 'realistic',
        string $trainingMode = 'realistic',
        ?string $situationType = null
    ): AiNegotiatorSession {
        return DB::transaction(function () use ($user, $sessionType, $difficulty, $trainingMode, $situationType) {
            User::query()->where('id', $user->id)->lockForUpdate()->firstOrFail();

            if ($this->getActiveSession($user) !== null) {
                throw new RuntimeException('active_session_exists');
            }

            if (!$this->credits->hasCredits($user)) {
                throw new RuntimeException('insufficient_credits');
            }

            $session = AiNegotiatorSession::create([
                'user_id' => $user->id,
                'session_type' => $sessionType,
                'session_state' => 'intake',
                'difficulty' => $difficulty,
                'training_mode' => $trainingMode,
                'situation_type' => $situationType,
                'started_at' => now(),
            ]);

            $this->stateMachine->bootstrapIntake($session, [
                'session_type' => $sessionType,
                'difficulty' => $difficulty,
            ]);

            return $session->fresh();
        });
    }

    /**
     * @return array{
     *   user_message: AiNegotiatorMessage,
     *   assistant_message: AiNegotiatorMessage,
     *   session_state: string,
     *   intake_complete: bool,
     *   opponent_persona: array<string, mixed>|null
     * }
     */
    public function submitIntakeMessage(AiNegotiatorSession $session, string $userMessage): array
    {
        $this->assertState($session, 'intake');

        $result = $this->intakeCoordinator->handleUserMessage($session, $userMessage, $this->llm);

        $opponentPersona = null;
        $session = $session->fresh();

        if ($result['intake_complete']) {
            $user = User::query()->findOrFail($session->user_id);

            $this->credits->consumeForSession($user, $session);

            $intakeData = $this->extractIntakeDataFromMessages($session);
            $opponentPersona = $this->scenarioBuilder->build(
                $intakeData,
                (string) $session->difficulty,
                $session->situation_type,
                $this->llm
            );

            $session->intake_data = $intakeData;
            $session->opponent_persona = $opponentPersona;
            $session->save();

            $this->stateMachine->transition($session, 'simulating', [
                'reason' => 'intake_complete',
            ]);

            $session = $session->fresh();
        }

        return [
            'user_message' => $result['user_message'],
            'assistant_message' => $result['assistant_message'],
            'session_state' => (string) $session->session_state,
            'intake_complete' => (bool) $result['intake_complete'],
            'opponent_persona' => $opponentPersona,
        ];
    }

    /**
     * @return array{
     *   user_message: AiNegotiatorMessage,
     *   assistant_message: AiNegotiatorMessage,
     *   session_state: string,
     *   evaluation: AiNegotiatorEvaluation|null
     * }
     */
    public function submitSimulationMessage(AiNegotiatorSession $session, string $userMessage): array
    {
        $this->assertState($session, 'simulating');

        $result = $this->opponentCoordinator->handleUserMessage($session, $userMessage, $this->llm);
        $evaluation = null;
        $session = $session->fresh();

        if (!empty($result['reached_message_limit'])) {
            $evaluation = $this->endSimulation($session);
            $session = $session->fresh();
        }

        return [
            'user_message' => $result['user_message'],
            'assistant_message' => $result['assistant_message'],
            'session_state' => (string) $session->session_state,
            'evaluation' => $evaluation,
        ];
    }

    public function endSimulation(AiNegotiatorSession $session): AiNegotiatorEvaluation
    {
        $this->assertState($session, 'simulating');

        $this->stateMachine->transition($session, 'evaluating', [
            'reason' => 'end_simulation',
        ]);

        $session = $session->fresh();

        try {
            $evaluation = $this->evaluationCoordinator->generate($session, $this->llm);
            $this->stateMachine->transition($session, 'completed', [
                'reason' => 'evaluation_ready',
                'evaluation_id' => $evaluation->id,
            ]);

            return $evaluation;
        } catch (Throwable $e) {
            Log::error('AI Negotiator evaluation failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            try {
                $this->stateMachine->transition($session->fresh(), 'abandoned', [
                    'reason' => 'evaluation_failed',
                ]);
            } catch (SessionStateException) {
                // already terminal / concurrent — ignore
            }

            throw $e;
        }
    }

    public function abandonSession(AiNegotiatorSession $session): void
    {
        $session = $session->fresh();
        $state = (string) $session->session_state;

        if ($this->stateMachine->isTerminal($state)) {
            throw SessionStateException::sessionAlreadyTerminal($state);
        }

        $this->stateMachine->transition($session, 'abandoned', [
            'reason' => 'user_abandon',
        ]);
    }

    public function getActiveSession(User $user): ?AiNegotiatorSession
    {
        return AiNegotiatorSession::query()
            ->where('user_id', $user->id)
            ->whereIn('session_state', ['intake', 'simulating', 'evaluating'])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    private function extractIntakeDataFromMessages(AiNegotiatorSession $session): array
    {
        $messages = AiNegotiatorMessage::query()
            ->where('ai_negotiator_session_id', $session->id)
            ->where('state_at_time', 'intake')
            ->orderBy('order_index')
            ->get();

        // Drop trailing assistant message that closed intake (token already stripped from stored content,
        // but the final assistant turn is the completion ack, not a question awaiting an answer).
        while ($messages->isNotEmpty() && $messages->last()->role === 'assistant') {
            $messages->pop();
        }

        $pairs = [];
        $pendingQuestion = null;

        foreach ($messages as $message) {
            if ($message->role === 'assistant') {
                $pendingQuestion = (string) $message->content;
                continue;
            }

            if ($message->role === 'user') {
                $pairs[] = [
                    'question' => $pendingQuestion ?? '',
                    'answer' => (string) $message->content,
                ];
                $pendingQuestion = null;
            }
        }

        return $pairs;
    }

    private function assertState(AiNegotiatorSession $session, string $expected): void
    {
        $session = $session->fresh();
        if ((string) $session->session_state !== $expected) {
            throw new RuntimeException("invalid_session_state:expected_{$expected}_got_{$session->session_state}");
        }
    }
}
