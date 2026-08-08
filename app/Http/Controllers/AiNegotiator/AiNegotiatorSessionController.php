<?php

namespace App\Http\Controllers\AiNegotiator;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiNegotiator\StartAiNegotiatorSessionRequest;
use App\Http\Requests\AiNegotiator\SubmitAiNegotiatorMessageRequest;
use App\Http\Resources\AiNegotiator\AiNegotiatorEvaluationResource;
use App\Http\Resources\AiNegotiator\AiNegotiatorMessageResource;
use App\Http\Resources\AiNegotiator\AiNegotiatorSessionResource;
use App\Http\Services\AiNegotiator\MessageService as AiNegotiatorMessageService;
use App\Http\Services\AiNegotiator\SessionService;
use App\Http\Services\AiNegotiator\StateMachine\SessionStateException;
use App\Models\AiNegotiator\AiNegotiatorSession;
use App\Models\Users\User;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderException;
use App\Services\MessageService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AiNegotiatorSessionController extends Controller
{
    public function __construct(
        protected SessionService $sessionService,
        protected AiNegotiatorMessageService $messageService,
    ) {}

    public function getActive(Request $request)
    {
        $user = $this->authenticatedUser();
        $session = $this->sessionService->getActiveSession($user);

        if (!$session) {
            return ResponseService::response([
                'success' => true,
                'data' => null,
                'message' => 'messages.ai_negotiator.no_active_session',
                'status' => 200,
            ]);
        }

        return ResponseService::response([
            'success' => true,
            'data' => $session,
            'resource' => AiNegotiatorSessionResource::class,
            'status' => 200,
        ]);
    }

    public function start(StartAiNegotiatorSessionRequest $request)
    {
        $user = $this->authenticatedUser();
        $data = $request->validated();

        try {
            $session = $this->sessionService->startSession(
                $user,
                $data['session_type'] ?? 'practice',
                $data['difficulty'] ?? 'realistic',
                $data['training_mode'] ?? 'realistic',
                $data['situation_type'] ?? null,
            );
        } catch (RuntimeException $e) {
            $this->mapStartException($e);
        }

        return ResponseService::response([
            'success' => true,
            'data' => $session,
            'message' => 'messages.ai_negotiator.session_started',
            'resource' => AiNegotiatorSessionResource::class,
            'status' => 200,
        ]);
    }

    public function submitMessage(SubmitAiNegotiatorMessageRequest $request, int $sessionId)
    {
        $user = $this->authenticatedUser();
        $session = $this->findOwnedSession($sessionId, $user->id);

        if (!in_array($session->session_state, ['intake', 'simulating'], true)) {
            MessageService::abort(409, 'messages.ai_negotiator.invalid_session_state');
        }

        try {
            if ($session->session_state === 'intake') {
                $result = $this->sessionService->submitIntakeMessage(
                    $session,
                    $request->validated('content')
                );

                $payload = [
                    'user_message' => (new AiNegotiatorMessageResource($result['user_message']))->resolve(),
                    'assistant_message' => (new AiNegotiatorMessageResource($result['assistant_message']))->resolve(),
                    'session_state' => $result['session_state'],
                    'intake_complete' => $result['intake_complete'],
                ];
            } else {
                $result = $this->sessionService->submitSimulationMessage(
                    $session,
                    $request->validated('content')
                );

                $payload = [
                    'user_message' => (new AiNegotiatorMessageResource($result['user_message']))->resolve(),
                    'assistant_message' => (new AiNegotiatorMessageResource($result['assistant_message']))->resolve(),
                    'session_state' => $result['session_state'],
                ];

                if (!empty($result['evaluation'])) {
                    $payload['evaluation'] = (new AiNegotiatorEvaluationResource(
                        $result['evaluation']->loadMissing('scores.rubricItem')
                    ))->resolve();
                }
            }
        } catch (LlmProviderException $e) {
            MessageService::abort(503, 'messages.ai_negotiator.llm_unavailable');
        } catch (RuntimeException $e) {
            $this->mapRuntimeException($e);
        } catch (Throwable $e) {
            if ($this->isLlmFailure($e)) {
                MessageService::abort(503, 'messages.ai_negotiator.llm_unavailable');
            }

            throw $e;
        }

        return ResponseService::response([
            'success' => true,
            'data' => $payload,
            'status' => 200,
        ]);
    }

    public function end(int $sessionId)
    {
        $user = $this->authenticatedUser();
        $session = $this->findOwnedSession($sessionId, $user->id);

        try {
            $evaluation = $this->sessionService->endSimulation($session);
            $evaluation->loadMissing('scores.rubricItem');
        } catch (LlmProviderException $e) {
            MessageService::abort(503, 'messages.ai_negotiator.llm_unavailable');
        } catch (RuntimeException $e) {
            $this->mapRuntimeException($e);
        } catch (SessionStateException $e) {
            MessageService::abort(409, 'messages.ai_negotiator.invalid_session_state');
        } catch (Throwable $e) {
            if ($this->isLlmFailure($e)) {
                MessageService::abort(503, 'messages.ai_negotiator.llm_unavailable');
            }

            throw $e;
        }

        return ResponseService::response([
            'success' => true,
            'data' => $evaluation,
            'message' => 'messages.ai_negotiator.session_ended',
            'resource' => AiNegotiatorEvaluationResource::class,
            'status' => 200,
        ]);
    }

    public function abandon(int $sessionId)
    {
        $user = $this->authenticatedUser();
        $session = $this->findOwnedSession($sessionId, $user->id);

        try {
            $this->sessionService->abandonSession($session);
        } catch (SessionStateException $e) {
            MessageService::abort(409, 'messages.ai_negotiator.invalid_session_state');
        }

        $session = $session->fresh();

        return ResponseService::response([
            'success' => true,
            'data' => $session,
            'message' => 'messages.ai_negotiator.session_abandoned',
            'resource' => AiNegotiatorSessionResource::class,
            'status' => 200,
        ]);
    }

    public function history(Request $request)
    {
        $user = $this->authenticatedUser();

        $sessions = AiNegotiatorSession::query()
            ->where('user_id', $user->id)
            ->whereIn('session_state', ['completed', 'abandoned'])
            ->with('evaluation')
            ->orderByDesc('created_at')
            ->paginate((int) $request->get('per_page', 15));

        return ResponseService::response([
            'success' => true,
            'data' => $sessions,
            'meta' => true,
            'resource' => AiNegotiatorSessionResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $sessionId)
    {
        $user = $this->authenticatedUser();
        $session = $this->findOwnedSession($sessionId, $user->id);
        $session->load(['messages', 'evaluation.scores.rubricItem']);

        return ResponseService::response([
            'success' => true,
            'data' => $session,
            'resource' => AiNegotiatorSessionResource::class,
            'status' => 200,
        ]);
    }

    private function authenticatedUser(): User
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        return $user;
    }

    private function findOwnedSession(int $sessionId, int $userId): AiNegotiatorSession
    {
        $session = AiNegotiatorSession::query()->find($sessionId);

        if (!$session) {
            MessageService::abort(404, 'messages.ai_negotiator.session_not_found');
        }

        if ((int) $session->user_id !== $userId) {
            MessageService::abort(403, 'messages.ai_negotiator.forbidden');
        }

        return $session;
    }

    private function mapStartException(RuntimeException $e): never
    {
        if ($e->getMessage() === 'active_session_exists') {
            MessageService::abort(409, 'messages.ai_negotiator.session_already_active');
        }

        if ($e->getMessage() === 'insufficient_credits') {
            MessageService::abort(402, 'messages.ai_negotiator.insufficient_credits');
        }

        throw $e;
    }

    private function mapRuntimeException(RuntimeException $e): never
    {
        if (str_starts_with($e->getMessage(), 'invalid_session_state')) {
            MessageService::abort(409, 'messages.ai_negotiator.invalid_session_state');
        }

        if ($e->getMessage() === 'insufficient_credits') {
            MessageService::abort(402, 'messages.ai_negotiator.insufficient_credits');
        }

        if ($this->isLlmFailure($e)) {
            MessageService::abort(503, 'messages.ai_negotiator.llm_unavailable');
        }

        throw $e;
    }

    private function isLlmFailure(Throwable $e): bool
    {
        $message = $e->getMessage();

        return $e instanceof LlmProviderException
            || $message === 'scenario_builder_failed'
            || $message === 'evaluation_generation_failed'
            || str_contains(strtolower($message), 'llm');
    }
}
