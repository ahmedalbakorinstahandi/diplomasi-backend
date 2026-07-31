<?php

namespace App\Http\Services\Negotiation;

use App\Models\Negotiation\NegotiationLevel;
use App\Models\Negotiation\NegotiationSituation;
use App\Models\Negotiation\UserNegotiationFinalTestAttempt;
use App\Models\Negotiation\UserNegotiationFinalTestAttemptAnswer;
use App\Models\Negotiation\UserNegotiationSituationAttempt;
use App\Models\Negotiation\UserNegotiationSituationAttemptAnswer;
use App\Services\MessageService;
use App\Services\NegotiationProgressService;
use App\Services\NegotiationQuizService;
use Illuminate\Support\Facades\DB;

/**
 * Negotiation quick-test / final-test attempt lifecycle + persistence.
 *
 * Partial-submit decision: REJECT incomplete answer sets (HTTP 400).
 * Lesson/scenario grade one question at a time and finish separately; this service accepts a
 * full batch submit that marks the attempt finished. Fewer than the required answers
 * (3 for quick test, 15 for final) is rejected and the attempt stays in_progress.
 */
class NegotiationAttemptService
{
    public function __construct(
        protected NegotiationQuizService $quizService,
        protected NegotiationProgressService $progressService,
    ) {}

    /**
     * Start a quick-test attempt for a situation.
     *
     * Return shape:
     * [
     *   'attempt' => UserNegotiationSituationAttempt,
     *   'client' => ['seed' => int, 'questions' => [...without correct_response_id]],
     *   'server' => ['seed' => int, 'questions' => [...with correct_response_id]],
     * ]
     */
    public function startQuickTest(int $situationId, int $userId): array
    {
        $situation = NegotiationSituation::with('negotiationResponses')->find($situationId);
        if (!$situation || !$this->progressService->isSituationPublished($situation)) {
            MessageService::abort(404, 'messages.negotiation.situation.not_found');
        }

        if (!$this->progressService->canAccessSituation($situation, $userId)) {
            $reason = $this->progressService->getSituationBlockingReason($situation, $userId);
            $messageKey = $reason === NegotiationProgressService::ACCESS_REASON_SUBSCRIPTION
                ? 'messages.negotiation.situation.subscription_required'
                : 'messages.negotiation.situation.locked';
            MessageService::abort(403, $messageKey);
        }

        $seed = $this->generateSeed();

        $attempt = UserNegotiationSituationAttempt::create([
            'user_id' => $userId,
            'negotiation_situation_id' => $situation->id,
            'status' => 'in_progress',
            'total_questions' => NegotiationQuizService::QUICK_TEST_QUESTION_COUNT,
            'correct_count' => 0,
            'seed' => $seed,
            'started_at' => now(),
        ]);

        // Marks in_progress when not yet completed; never downgrades completed
        // (updateSituationProgress treats any finished attempt as permanent completion).
        $this->progressService->updateSituationProgress($situation->id, $userId);

        $serverQuiz = $this->quizService->buildQuickTest($situation, $seed);

        return [
            'attempt' => $attempt->fresh(),
            'client' => $this->toClientSafeQuiz($serverQuiz),
            'server' => $serverQuiz,
        ];
    }

    /**
     * Submit a full quick-test answer set and finish the attempt.
     *
     * @param  list<array{asked_style: string, selected_response_id?: int|null}>  $answers
     */
    public function submitQuickTest(int $attemptId, int $userId, array $answers): array
    {
        $attempt = UserNegotiationSituationAttempt::where('id', $attemptId)
            ->where('user_id', $userId)
            ->first();

        if (!$attempt) {
            MessageService::abort(404, 'messages.attempt.not_found');
        }

        if ($attempt->status !== 'in_progress') {
            MessageService::abort(400, 'messages.attempt.already_finished');
        }

        $this->assertFullQuickTestAnswers($answers);

        $situation = NegotiationSituation::with('negotiationResponses')
            ->find($attempt->negotiation_situation_id);

        if (!$situation) {
            MessageService::abort(404, 'messages.negotiation.situation.not_found');
        }

        $gradeResults = [];

        DB::transaction(function () use ($attempt, $situation, $answers, &$gradeResults) {
            foreach ($answers as $answer) {
                $askedStyle = (string) $answer['asked_style'];
                $selectedId = array_key_exists('selected_response_id', $answer)
                    ? ($answer['selected_response_id'] !== null ? (int) $answer['selected_response_id'] : null)
                    : null;

                $graded = $this->quizService->gradeAnswer($situation, $askedStyle, $selectedId);
                $gradeResults[] = $graded;

                $row = UserNegotiationSituationAttemptAnswer::withTrashed()->firstOrNew([
                    'user_negotiation_situation_attempt_id' => $attempt->id,
                    'asked_style' => $askedStyle,
                ]);

                if ($row->trashed()) {
                    $row->restore();
                }

                $row->negotiation_situation_id = $situation->id;
                $row->selected_negotiation_response_id = $selectedId;
                $row->is_correct = $graded['is_correct'];
                $row->answered_at = now();
                $row->save();
            }

            $scored = $this->quizService->scoreAttempt($gradeResults);

            $attempt->correct_count = $scored['correct_count'];
            $attempt->score = $scored['score'];
            $attempt->status = 'finished';
            $attempt->finished_at = now();
            $attempt->save();
        });

        $this->progressService->updateSituationProgress((int) $situation->id, $userId);

        $attempt->refresh();

        return [
            'attempt' => $attempt,
            'results' => $gradeResults,
            'summary' => [
                'correct_count' => (int) $attempt->correct_count,
                'total' => (int) $attempt->total_questions,
                'score' => (float) $attempt->score,
            ],
        ];
    }

    /**
     * Start a final light-test attempt for a completed level.
     *
     * Intentionally non-gating: does not call any progress / level-completion method.
     */
    public function startFinalTest(int $levelId, int $userId): array
    {
        $level = NegotiationLevel::find($levelId);
        if (!$level || !$this->progressService->isLevelPublished($level)) {
            MessageService::abort(404, 'messages.negotiation.level.not_found');
        }

        if (!$this->progressService->isNegotiationLevelCompleted($level, $userId)) {
            MessageService::abort(403, 'messages.negotiation.level.final_test_requires_completion');
        }

        $seed = $this->generateSeed();

        $attempt = UserNegotiationFinalTestAttempt::create([
            'user_id' => $userId,
            'negotiation_level_id' => $level->id,
            'status' => 'in_progress',
            'total_questions' => NegotiationQuizService::FINAL_TEST_QUESTION_COUNT,
            'correct_count' => 0,
            'seed' => $seed,
            'started_at' => now(),
        ]);

        $serverQuiz = $this->quizService->buildFinalTest($level, $seed);

        return [
            'attempt' => $attempt->fresh(),
            'client' => $this->toClientSafeQuiz($serverQuiz),
            'server' => $serverQuiz,
        ];
    }

    /**
     * Submit a full final-test answer set and finish the attempt.
     *
     * The final light test is intentionally non-gating: score is stored for reference only.
     * Do NOT call NegotiationProgressService here.
     *
     * @param  list<array{situation_id: int, asked_style: string, selected_response_id?: int|null}>  $answers
     */
    public function submitFinalTest(int $attemptId, int $userId, array $answers): array
    {
        $attempt = UserNegotiationFinalTestAttempt::where('id', $attemptId)
            ->where('user_id', $userId)
            ->first();

        if (!$attempt) {
            MessageService::abort(404, 'messages.attempt.not_found');
        }

        if ($attempt->status !== 'in_progress') {
            MessageService::abort(400, 'messages.attempt.already_finished');
        }

        $level = NegotiationLevel::find($attempt->negotiation_level_id);
        if (!$level) {
            MessageService::abort(404, 'messages.negotiation.level.not_found');
        }

        $quiz = $this->quizService->buildFinalTest($level, (int) $attempt->seed);
        $this->assertFullFinalTestAnswers($answers, $quiz['questions']);

        $situationsById = NegotiationSituation::with('negotiationResponses')
            ->whereIn('id', collect($answers)->pluck('situation_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $gradeResults = [];

        DB::transaction(function () use ($attempt, $answers, $situationsById, &$gradeResults) {
            foreach ($answers as $answer) {
                $situationId = (int) $answer['situation_id'];
                $askedStyle = (string) $answer['asked_style'];
                $selectedId = array_key_exists('selected_response_id', $answer)
                    ? ($answer['selected_response_id'] !== null ? (int) $answer['selected_response_id'] : null)
                    : null;

                $situation = $situationsById->get($situationId);
                if (!$situation) {
                    MessageService::abort(400, 'messages.negotiation.situation.not_found');
                }

                $graded = $this->quizService->gradeAnswer($situation, $askedStyle, $selectedId);
                $gradeResults[] = array_merge($graded, [
                    'situation_id' => $situationId,
                ]);

                $row = UserNegotiationFinalTestAttemptAnswer::withTrashed()->firstOrNew([
                    'user_negotiation_final_test_attempt_id' => $attempt->id,
                    'negotiation_situation_id' => $situationId,
                    'asked_style' => $askedStyle,
                ]);

                if ($row->trashed()) {
                    $row->restore();
                }

                $row->selected_negotiation_response_id = $selectedId;
                $row->is_correct = $graded['is_correct'];
                $row->answered_at = now();
                $row->save();
            }

            $scored = $this->quizService->scoreAttempt($gradeResults);

            $attempt->correct_count = $scored['correct_count'];
            $attempt->score = $scored['score'];
            $attempt->status = 'finished';
            $attempt->finished_at = now();
            $attempt->save();
        });

        // Non-gating: no progress / level-completion update.

        $attempt->refresh();

        return [
            'attempt' => $attempt,
            'results' => $gradeResults,
            'summary' => [
                'correct_count' => (int) $attempt->correct_count,
                'total' => (int) $attempt->total_questions,
                'score' => (float) $attempt->score,
            ],
        ];
    }

    /**
     * Archive of quick-test attempts for a situation (newest first).
     */
    public function listSituationAttempts(int $situationId, int $userId): array
    {
        $situation = NegotiationSituation::find($situationId);
        if (!$situation) {
            MessageService::abort(404, 'messages.negotiation.situation.not_found');
        }

        return UserNegotiationSituationAttempt::where('negotiation_situation_id', $situationId)
            ->where('user_id', $userId)
            ->orderBy('started_at', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->all();
    }

    /**
     * Archive of final-test attempts for a level (newest first).
     */
    public function listFinalTestAttempts(int $levelId, int $userId): array
    {
        $level = NegotiationLevel::find($levelId);
        if (!$level) {
            MessageService::abort(404, 'messages.negotiation.level.not_found');
        }

        return UserNegotiationFinalTestAttempt::where('negotiation_level_id', $levelId)
            ->where('user_id', $userId)
            ->orderBy('started_at', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->all();
    }

    /**
     * Review a quick-test attempt: reproduce option order from seed + past selections.
     *
     * @return array{
     *   attempt: UserNegotiationSituationAttempt,
     *   questions: list<array{
     *     situation_id: int,
     *     asked_style: string,
     *     options: list<array{id: int, response_text: string}>,
     *     selected_response_id: int|null,
     *     is_correct: bool|null,
     *     correct_response_id: int,
     *     feedback: string|null
     *   }>
     * }
     */
    public function reviewQuickTest(int $attemptId, int $userId): array
    {
        $attempt = UserNegotiationSituationAttempt::where('id', $attemptId)
            ->where('user_id', $userId)
            ->first();

        if (!$attempt) {
            MessageService::abort(404, 'messages.attempt.not_found');
        }

        $situation = NegotiationSituation::with('negotiationResponses')
            ->find($attempt->negotiation_situation_id);

        if (!$situation) {
            MessageService::abort(404, 'messages.negotiation.situation.not_found');
        }

        $quiz = $this->quizService->buildQuickTest($situation, (int) $attempt->seed);
        $answersByStyle = UserNegotiationSituationAttemptAnswer::where(
            'user_negotiation_situation_attempt_id',
            $attempt->id
        )->get()->keyBy('asked_style');

        $questions = [];
        foreach ($quiz['questions'] as $question) {
            $answer = $answersByStyle->get($question['asked_style']);
            $questions[] = [
                'situation_id' => $question['situation_id'],
                'asked_style' => $question['asked_style'],
                'options' => $question['options'],
                'selected_response_id' => $answer?->selected_negotiation_response_id,
                'is_correct' => $answer !== null ? (bool) $answer->is_correct : null,
                'correct_response_id' => $question['correct_response_id'],
                'feedback' => $this->feedbackForCorrectResponse(
                    $situation,
                    (int) $question['correct_response_id']
                ),
            ];
        }

        return [
            'attempt' => $attempt,
            'questions' => $questions,
        ];
    }

    /**
     * Review a final-test attempt: reproduce option order from seed + past selections.
     */
    public function reviewFinalTest(int $attemptId, int $userId): array
    {
        $attempt = UserNegotiationFinalTestAttempt::where('id', $attemptId)
            ->where('user_id', $userId)
            ->first();

        if (!$attempt) {
            MessageService::abort(404, 'messages.attempt.not_found');
        }

        $level = NegotiationLevel::find($attempt->negotiation_level_id);
        if (!$level) {
            MessageService::abort(404, 'messages.negotiation.level.not_found');
        }

        $quiz = $this->quizService->buildFinalTest($level, (int) $attempt->seed);
        $answers = UserNegotiationFinalTestAttemptAnswer::where(
            'user_negotiation_final_test_attempt_id',
            $attempt->id
        )->get();

        $answersByKey = [];
        foreach ($answers as $answer) {
            $answersByKey[$answer->negotiation_situation_id . ':' . $answer->asked_style] = $answer;
        }

        $situationIds = collect($quiz['questions'])->pluck('situation_id')->unique()->all();
        $situationsById = NegotiationSituation::with('negotiationResponses')
            ->whereIn('id', $situationIds)
            ->get()
            ->keyBy('id');

        $questions = [];
        foreach ($quiz['questions'] as $question) {
            $key = $question['situation_id'] . ':' . $question['asked_style'];
            $answer = $answersByKey[$key] ?? null;
            $situation = $situationsById->get($question['situation_id']);

            $questions[] = [
                'situation_id' => $question['situation_id'],
                'asked_style' => $question['asked_style'],
                'options' => $question['options'],
                'selected_response_id' => $answer?->selected_negotiation_response_id,
                'is_correct' => $answer !== null ? (bool) $answer->is_correct : null,
                'correct_response_id' => $question['correct_response_id'],
                'feedback' => $situation
                    ? $this->feedbackForCorrectResponse($situation, (int) $question['correct_response_id'])
                    : null,
            ];
        }

        return [
            'attempt' => $attempt,
            'questions' => $questions,
        ];
    }

    private function feedbackForCorrectResponse(NegotiationSituation $situation, int $correctResponseId): ?string
    {
        if (!$situation->relationLoaded('negotiationResponses')) {
            $situation->load('negotiationResponses');
        }

        $response = $situation->negotiationResponses->firstWhere('id', $correctResponseId);

        return $response?->explanation;
    }

    /**
     * Strip correct_response_id from questions for client payloads.
     */
    private function toClientSafeQuiz(array $quiz): array
    {
        return [
            'seed' => $quiz['seed'],
            'questions' => array_map(static function (array $question): array {
                return [
                    'situation_id' => $question['situation_id'],
                    'asked_style' => $question['asked_style'],
                    'options' => $question['options'],
                ];
            }, $quiz['questions']),
        ];
    }

    private function generateSeed(): int
    {
        return random_int(1, 0x7fffffff);
    }

    /**
     * Require exactly one answer per style (3 total). Reject partial submits.
     */
    private function assertFullQuickTestAnswers(array $answers): void
    {
        if (count($answers) !== NegotiationQuizService::QUICK_TEST_QUESTION_COUNT) {
            MessageService::abort(400, 'messages.negotiation.attempt.incomplete_answers');
        }

        $styles = [];
        foreach ($answers as $answer) {
            if (!isset($answer['asked_style'])) {
                MessageService::abort(400, 'messages.negotiation.attempt.incomplete_answers');
            }
            $style = (string) $answer['asked_style'];
            if (!in_array($style, NegotiationQuizService::STYLES, true) || isset($styles[$style])) {
                MessageService::abort(400, 'messages.negotiation.attempt.incomplete_answers');
            }
            $styles[$style] = true;
        }

        foreach (NegotiationQuizService::STYLES as $required) {
            if (!isset($styles[$required])) {
                MessageService::abort(400, 'messages.negotiation.attempt.incomplete_answers');
            }
        }
    }

    /**
     * Require exactly the 15 drawn (situation, style) pairs for this attempt's seed.
     */
    private function assertFullFinalTestAnswers(array $answers, array $drawnQuestions): void
    {
        if (count($answers) !== NegotiationQuizService::FINAL_TEST_QUESTION_COUNT) {
            MessageService::abort(400, 'messages.negotiation.attempt.incomplete_answers');
        }

        $expected = [];
        foreach ($drawnQuestions as $question) {
            $expected[$question['situation_id'] . ':' . $question['asked_style']] = true;
        }

        $seen = [];
        foreach ($answers as $answer) {
            if (!isset($answer['situation_id'], $answer['asked_style'])) {
                MessageService::abort(400, 'messages.negotiation.attempt.incomplete_answers');
            }
            $key = (int) $answer['situation_id'] . ':' . (string) $answer['asked_style'];
            if (!isset($expected[$key]) || isset($seen[$key])) {
                MessageService::abort(400, 'messages.negotiation.attempt.incomplete_answers');
            }
            $seen[$key] = true;
        }

        if (count($seen) !== count($expected)) {
            MessageService::abort(400, 'messages.negotiation.attempt.incomplete_answers');
        }
    }
}
