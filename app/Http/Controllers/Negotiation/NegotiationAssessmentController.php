<?php

namespace App\Http\Controllers\Negotiation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Negotiation\SubmitFinalTestRequest;
use App\Http\Requests\Negotiation\SubmitQuickTestRequest;
use App\Http\Resources\Negotiation\NegotiationAttemptArchiveItemResource;
use App\Http\Resources\Negotiation\NegotiationClientQuizResource;
use App\Http\Resources\Negotiation\NegotiationGradedAnswerResource;
use App\Http\Resources\Negotiation\NegotiationReviewQuestionResource;
use App\Http\Services\Negotiation\NegotiationAttemptService;
use App\Models\Users\User;
use App\Services\MessageService;
use App\Services\ResponseService;

class NegotiationAssessmentController extends Controller
{
    public function __construct(
        protected NegotiationAttemptService $attemptService,
    ) {}

    public function startQuickTest(int $situation)
    {
        $user = $this->requireUser();

        $result = $this->attemptService->startQuickTest($situation, $user->id);

        // SECURITY: only serialize the client-safe quiz (never $result['server']).
        return ResponseService::response([
            'success' => true,
            'data' => [
                'attempt_id' => $result['attempt']->id,
                'status' => $result['attempt']->status,
                'quiz' => (new NegotiationClientQuizResource($result['client']))->resolve(),
            ],
            'status' => 201,
        ]);
    }

    public function submitQuickTest(SubmitQuickTestRequest $request, int $attempt)
    {
        $user = $this->requireUser();

        $result = $this->attemptService->submitQuickTest(
            $attempt,
            $user->id,
            $request->validated('answers')
        );

        return ResponseService::response([
            'success' => true,
            'data' => [
                'attempt_id' => $result['attempt']->id,
                'status' => $result['attempt']->status,
                'summary' => $result['summary'],
                'results' => NegotiationGradedAnswerResource::collection(
                    collect($result['results'])
                )->resolve(),
            ],
            'status' => 200,
        ]);
    }

    public function startFinalTest(int $level)
    {
        $user = $this->requireUser();

        $result = $this->attemptService->startFinalTest($level, $user->id);

        // SECURITY: only serialize the client-safe quiz (never $result['server']).
        return ResponseService::response([
            'success' => true,
            'data' => [
                'attempt_id' => $result['attempt']->id,
                'status' => $result['attempt']->status,
                'quiz' => (new NegotiationClientQuizResource($result['client']))->resolve(),
            ],
            'status' => 201,
        ]);
    }

    public function submitFinalTest(SubmitFinalTestRequest $request, int $attempt)
    {
        $user = $this->requireUser();

        $result = $this->attemptService->submitFinalTest(
            $attempt,
            $user->id,
            $request->validated('answers')
        );

        return ResponseService::response([
            'success' => true,
            'data' => [
                'attempt_id' => $result['attempt']->id,
                'status' => $result['attempt']->status,
                'summary' => $result['summary'],
                'results' => NegotiationGradedAnswerResource::collection(
                    collect($result['results'])
                )->resolve(),
            ],
            'status' => 200,
        ]);
    }

    public function listSituationAttempts(int $situation)
    {
        $user = $this->requireUser();

        $attempts = $this->attemptService->listSituationAttempts($situation, $user->id);

        return ResponseService::response([
            'success' => true,
            'data' => NegotiationAttemptArchiveItemResource::collection(collect($attempts))->resolve(),
            'status' => 200,
        ]);
    }

    public function listFinalTestAttempts(int $level)
    {
        $user = $this->requireUser();

        $attempts = $this->attemptService->listFinalTestAttempts($level, $user->id);

        return ResponseService::response([
            'success' => true,
            'data' => NegotiationAttemptArchiveItemResource::collection(collect($attempts))->resolve(),
            'status' => 200,
        ]);
    }

    public function reviewQuickTest(int $attempt)
    {
        $user = $this->requireUser();

        $result = $this->attemptService->reviewQuickTest($attempt, $user->id);

        return ResponseService::response([
            'success' => true,
            'data' => [
                'attempt' => (new NegotiationAttemptArchiveItemResource($result['attempt']))->resolve(),
                'mode' => 'review',
                'questions' => NegotiationReviewQuestionResource::collection(
                    collect($result['questions'])
                )->resolve(),
            ],
            'status' => 200,
        ]);
    }

    public function reviewFinalTest(int $attempt)
    {
        $user = $this->requireUser();

        $result = $this->attemptService->reviewFinalTest($attempt, $user->id);

        return ResponseService::response([
            'success' => true,
            'data' => [
                'attempt' => (new NegotiationAttemptArchiveItemResource($result['attempt']))->resolve(),
                'mode' => 'review',
                'questions' => NegotiationReviewQuestionResource::collection(
                    collect($result['questions'])
                )->resolve(),
            ],
            'status' => 200,
        ]);
    }

    private function requireUser(): User
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        return $user;
    }
}
