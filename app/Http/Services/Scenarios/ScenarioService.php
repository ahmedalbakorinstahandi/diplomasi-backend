<?php

namespace App\Http\Services\Scenarios;

use App\Http\Permissions\Scenarios\ScenarioPermission;
use App\Models\Learning\LevelTrack;
use App\Models\Scenarios\Scenario;
use App\Models\Scenarios\ScenarioQuestion;
use App\Models\Scenarios\ScenarioQuestionOption;
use App\Models\Scenarios\UserScenarioAttempt;
use App\Models\Scenarios\UserScenarioQuestionAnswer;
use App\Models\Scenarios\UserScenarioAnswerOption;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;
use App\Services\TrackProgressService;
use App\Models\Users\User;

class ScenarioService
{
    public function index($filters = [])
    {
        $query = Scenario::query()->with([
            'level',
            // 'startQuestion',
            // 'scenarioQuestions',
            // 'userScenarioAttempts'
        ]);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'order_index';
        $filters['sort_order'] = $filters['sort_order'] ?? 'asc';

        $searchFields = ['title'];
        $numericFields = ['order_index'];
        $dateFields = ['created_at'];
        $exactMatchFields = ['is_published', 'is_free', 'level_id'];
        $inFields = [];

        $query = ScenarioPermission::filterIndex($query);

        $query = FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );

        return $query;
    }

    public function show(int $id)
    {
        $scenario = Scenario::where('id', $id)->first();
        if (!$scenario) {
            MessageService::abort(404, 'messages.scenario.not_found');
        }

        $scenario->load([
            'level',
            'startQuestion',
            // 'scenarioQuestions',
            // 'userScenarioAttempts'
        ]);

        return $scenario;
    }

    public function create($data)
    {
        // التحقق من start_question_id عند محاولة النشر
        if (isset($data['is_published']) && $data['is_published'] === true) {
            if (empty($data['start_question_id'])) {
                MessageService::abort(422, 'messages.scenario.cannot_publish_without_start_question');
            }
        }
        
        $scenario = Scenario::create($data);

        OrderHelper::assign($scenario, 'order_index');

        // Create or update LevelTrack
        $this->syncLevelTrack($scenario);

        $scenario = $this->show($scenario->id);

        return $scenario;
    }

    public function update($data, $scenario)
    {
        $oldLevelId = $scenario->level_id;
        
        // التحقق من start_question_id عند محاولة النشر
        if (isset($data['is_published']) && $data['is_published'] === true) {
            $finalStartQuestionId = $data['start_question_id'] ?? $scenario->start_question_id;
            if (!$finalStartQuestionId) {
                MessageService::abort(422, 'messages.scenario.cannot_publish_without_start_question');
            }

            $flowValidation = app(ScenarioQuestionAdminService::class)->validateFlow((int) $scenario->id, true);
            if (!$flowValidation['success']) {
                MessageService::abort(422, $flowValidation['message']);
            }
        }
        
        $scenario->update($data);

        // If level_id changed, update LevelTrack
        if (isset($data['level_id']) && $data['level_id'] != $oldLevelId) {
            // Delete old LevelTrack
            $oldLevelTrack = LevelTrack::where('trackable_id', $scenario->id)
                ->where('trackable_type', Scenario::class)
                ->first();
            if ($oldLevelTrack) {
                $oldLevelTrack->delete();
            }
            
            // Create new LevelTrack for new level
            $this->syncLevelTrack($scenario);
        } else {
            // Just sync to ensure it exists
            $this->syncLevelTrack($scenario);
        }

        $scenario = $this->show($scenario->id);

        return $scenario;
    }

    public function delete($scenario)
    {
        // Delete related records
        $scenario->scenarioQuestions()->delete();
        $scenario->userScenarioAttempts()->delete();
        
        // Delete LevelTrack
        $levelTrack = LevelTrack::where('trackable_id', $scenario->id)
            ->where('trackable_type', Scenario::class)
            ->first();
        if ($levelTrack) {
            $levelTrack->delete();
        }

        $scenario->delete();
    }

    public function reorder($scenario, $validatedData)
    {
        OrderHelper::reorder($scenario, $validatedData['new_order_index'], 'order_index');

        return $this->show($scenario->id);
    }

    public function startAttempt($scenario)
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        // Check if scenario is accessible (previous track is completed)
        $levelTrack = LevelTrack::where('trackable_id', $scenario->id)
            ->where('trackable_type', Scenario::class)
            ->first();

        if ($levelTrack) {
            $trackProgressService = app(TrackProgressService::class);
            if (!$trackProgressService->canAccessTrack($levelTrack, $user->id)) {
                MessageService::abort(403, 'messages.scenario.locked');
            }
        }

        // Check if there's an existing in-progress attempt
        $existingAttempt = UserScenarioAttempt::where('user_id', $user->id)
            ->where('scenario_id', $scenario->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existingAttempt) {
            return $existingAttempt;
        }

        // Validate that scenario has a start question
        if (!$scenario->start_question_id) {
            MessageService::abort(400, 'messages.scenario.no_start_question');
        }

        $attempt = UserScenarioAttempt::create([
            'user_id' => $user->id,
            'scenario_id' => $scenario->id,
            'status' => 'in_progress',
            'progress_percentage' => 0,
            'track_status' => 'open',
            'is_completed' => false,
            'started_at' => now(),
        ]);

        // Update progress (initial state)
        $trackProgressService = app(TrackProgressService::class);
        $trackProgressService->calculateAndUpdateScenarioProgress($scenario, $user->id, $attempt);

        $attempt->load(['scenario']);

        return $attempt;
    }

    /**
     * Get the current question for an attempt
     */
    public function getCurrentQuestion($attemptId)
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $attempt = UserScenarioAttempt::where('id', $attemptId)
            ->where('user_id', $user->id)
            ->with(['scenario'])
            ->first();

        if (!$attempt) {
            MessageService::abort(404, 'messages.attempt.not_found');
        }

        if ($attempt->status === 'finished') {
            MessageService::abort(400, 'messages.attempt.already_finished');
        }

        // Get the last answered question to determine the next question
        $lastAnswer = UserScenarioQuestionAnswer::where('attempt_id', $attemptId)
            ->orderBy('step_index', 'desc')
            ->with(['userScenarioAnswerOptions.scenarioQuestionOption.nextQuestion'])
            ->first();

        $currentQuestion = null;

        if (!$lastAnswer) {
            // No answers yet, start with the scenario's start question
            $currentQuestion = ScenarioQuestion::where('id', $attempt->scenario->start_question_id)
                ->with(['scenarioQuestionOptions.nextQuestion'])
                ->first();
        } else {
            // Get the next question from the last answer's selected option
            $lastAnswerOption = $lastAnswer->userScenarioAnswerOptions->first();
            if ($lastAnswerOption && $lastAnswerOption->scenarioQuestionOption) {
                $nextQuestionId = $lastAnswerOption->scenarioQuestionOption->next_question_id;
                if ($nextQuestionId) {
                    $currentQuestion = ScenarioQuestion::where('id', $nextQuestionId)
                        ->with(['scenarioQuestionOptions.nextQuestion'])
                        ->first();
                }
            }
        }

        if (!$currentQuestion) {
            // No more questions, scenario is finished
            $attempt->status = 'finished';
            $attempt->finished_at = now();
            $attempt->save();

            // Update progress after finishing (100%)
            $trackProgressService = app(TrackProgressService::class);
            $trackProgressService->calculateAndUpdateScenarioProgress($attempt->scenario, $attempt->user_id, $attempt);

            return [
                'finished' => true,
                'message' => 'messages.scenario.finished',
            ];
        }

        // في السيناريوهات المسارية، قد نعود لنفس السؤال مرة أخرى (loop/retry).
        // لذلك نعيده دائماً كسؤال نشط قابل للإجابة من جديد.
        return [
            'question' => $currentQuestion,
            'answered' => false,
            'answer' => null,
        ];
    }

    public function submitAnswer($attemptId, $questionId, $optionId = null, $answerText = null)
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $attempt = UserScenarioAttempt::where('id', $attemptId)
            ->where('user_id', $user->id)
            ->first();

        if (!$attempt) {
            MessageService::abort(404, 'messages.attempt.not_found');
        }

        if ($attempt->status === 'finished') {
            MessageService::abort(400, 'messages.attempt.already_finished');
        }

        // Validate question exists and belongs to the scenario
        $question = ScenarioQuestion::where('id', $questionId)
            ->where('scenario_id', $attempt->scenario_id)
            ->with(['scenarioQuestionOptions'])
            ->first();

        if (!$question) {
            MessageService::abort(404, 'messages.question.not_found');
        }

        // نسمح بتكرار الإجابة لنفس السؤال في حال المسارات الحلقية (العودة للبداية/التجربة مرة أخرى).

        // Validate answer based on question type
        if ($question->type === 'single_choice' || $question->type === 'true_false') {
            if (!$optionId) {
                MessageService::abort(400, 'messages.answer.option_id_required');
            }

            $option = ScenarioQuestionOption::where('id', $optionId)
                ->where('question_id', $questionId)
                ->first();

            if (!$option) {
                MessageService::abort(400, 'messages.answer.invalid_option');
            }
        }

        // Calculate step_index (number of previous answers + 1)
        $stepIndex = UserScenarioQuestionAnswer::where('attempt_id', $attemptId)
            ->max('step_index') ?? 0;
        $stepIndex += 1;

        // Create the answer record
        $answer = UserScenarioQuestionAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $questionId,
            'step_index' => $stepIndex,
            'answered_at' => now(),
            'time_spent' => null, // Can be calculated on frontend and sent
        ]);

        // Save the selected option if provided
        if ($optionId) {
            UserScenarioAnswerOption::create([
                'user_answer_id' => $answer->id,
                'option_id' => $optionId,
            ]);
        }

        // Get the next question based on the selected option
        $nextQuestionId = null;
        $selectedOption = null;
        if ($optionId) {
            $selectedOption = ScenarioQuestionOption::where('id', $optionId)->first();
            if ($selectedOption) {
                $nextQuestionId = $selectedOption->next_question_id;
            }
        }

        // If no next question, finish the attempt
        $isFinished = false;
        if (!$nextQuestionId) {
            $attempt->status = 'finished';
            $attempt->finished_at = now();
            $attempt->save();
            $isFinished = true;

            // Update progress after finishing (100%)
            $trackProgressService = app(TrackProgressService::class);
            $trackProgressService->calculateAndUpdateScenarioProgress($attempt->scenario, $attempt->user_id, $attempt);
        }

        $answer->load(['userScenarioAnswerOptions.scenarioQuestionOption']);

        return [
            'answer' => $answer,
            'next_question_id' => $nextQuestionId,
            'finished' => $isFinished,
            'feedback_text' => $selectedOption?->feedback_text,
            'explanation' => $question->explanation,
        ];
    }

    /**
     * Finish a scenario attempt
     */
    public function finishAttempt($attemptId)
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $attempt = UserScenarioAttempt::where('id', $attemptId)
            ->where('user_id', $user->id)
            ->first();

        if (!$attempt) {
            MessageService::abort(404, 'messages.attempt.not_found');
        }

        if ($attempt->status === 'finished') {
            MessageService::abort(400, 'messages.attempt.already_finished');
        }

        $attempt->status = 'finished';
        $attempt->finished_at = now();
        $attempt->save();

        // Update progress after finishing (100%)
        $trackProgressService = app(TrackProgressService::class);
        $trackProgressService->calculateAndUpdateScenarioProgress($attempt->scenario, $attempt->user_id, $attempt);

        return $attempt;
    }

    /**
     * Sync LevelTrack for a scenario
     */
    private function syncLevelTrack(Scenario $scenario)
    {
        $levelTrack = LevelTrack::withTrashed()->updateOrCreate(
            [
                'level_id' => $scenario->level_id,
                'trackable_id' => $scenario->id,
                'trackable_type' => Scenario::class,
            ],
            [
                'deleted_at' => null,
            ]
        );

        if ($levelTrack->wasRecentlyCreated || $levelTrack->order_index === null) {
            OrderHelper::assign($levelTrack, 'order_index');
        }
    }

    /**
     * Mark description as read for a scenario attempt
     *
     * @param int $attemptId
     * @param int $userId
     * @return UserScenarioAttempt
     */
    public function markDescriptionRead($attemptId, $userId)
    {
        $attempt = UserScenarioAttempt::find($attemptId);
        if (!$attempt) {
            MessageService::abort(404, 'messages.attempt.not_found');
        }

        if ($attempt->user_id != $userId) {
            MessageService::abort(403, 'messages.attempt.unauthorized');
        }

        if ($attempt->description_read) {
            return $attempt; // Already read
        }

        $attempt->description_read = true;
        $attempt->description_read_at = now();
        $attempt->save();

        // Update progress after marking description as read
        $trackProgressService = app(TrackProgressService::class);
        $trackProgressService->calculateAndUpdateScenarioProgress($attempt->scenario, $attempt->user_id, $attempt);

        return $attempt;
    }
}

