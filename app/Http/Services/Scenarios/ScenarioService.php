<?php

namespace App\Http\Services\Scenarios;

use App\Http\Permissions\Scenarios\ScenarioPermission;
use App\Models\Scenarios\Scenario;
use App\Models\Scenarios\UserScenarioAttempt;
use App\Models\Scenarios\UserScenarioQuestionAnswer;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;
use App\Models\Users\User;

class ScenarioService
{
    public function index($filters = [])
    {
        $query = Scenario::query()->with([
            'level',
            'startQuestion',
            'scenarioQuestions',
            'userScenarioAttempts'
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
            'scenarioQuestions',
            'userScenarioAttempts'
        ]);

        return $scenario;
    }

    public function create($data)
    {
        $scenario = Scenario::create($data);

        OrderHelper::assign($scenario, 'order_index');

        $scenario = $this->show($scenario->id);

        return $scenario;
    }

    public function update($data, $scenario)
    {
        $scenario->update($data);

        $scenario = $this->show($scenario->id);

        return $scenario;
    }

    public function delete($scenario)
    {
        // Delete related records
        $scenario->scenarioQuestions()->delete();
        $scenario->userScenarioAttempts()->delete();

        $scenario->delete();
    }

    public function reorder($scenario, $validatedData)
    {
        OrderHelper::reorder($scenario, $validatedData['new_order_index'], 'order_index');

        return $this->show($scenario->id);
    }

    public function startAttempt($scenarioId)
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $scenario = $this->show($scenarioId);

        $attempt = UserScenarioAttempt::create([
            'user_id' => $user->id,
            'scenario_id' => $scenario->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return $attempt;
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

        $answer = UserScenarioQuestionAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $questionId,
            'option_id' => $optionId,
            'answer_text' => $answerText,
        ]);

        return $answer;
    }
}

