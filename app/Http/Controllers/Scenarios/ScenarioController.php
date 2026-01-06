<?php

namespace App\Http\Controllers\Scenarios;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Scenarios\ScenarioPermission;
use App\Http\Requests\Scenarios\CreateScenarioRequest;
use App\Http\Requests\Scenarios\ReOrderScenarioRequest;
use App\Http\Requests\Scenarios\StartScenarioAttemptRequest;
use App\Http\Requests\Scenarios\SubmitScenarioAnswerRequest;
use App\Http\Requests\Scenarios\UpdateScenarioRequest;
use App\Http\Resources\Scenarios\ScenarioResource;
use App\Http\Services\Scenarios\ScenarioService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class ScenarioController extends Controller
{
    protected $scenarioService;

    public function __construct(ScenarioService $scenarioService)
    {
        $this->scenarioService = $scenarioService;
    }

    public function index(Request $request, $message = null)
    {
        ScenarioPermission::canView();

        $scenarios = $this->scenarioService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $scenarios,
            'meta' => true,
            'resource' => ScenarioResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        ScenarioPermission::canView();

        $scenario = $this->scenarioService->show($id);
        ScenarioPermission::canShow($scenario);

        return ResponseService::response([
            'success' => true,
            'data' => $scenario,
            'resource' => ScenarioResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateScenarioRequest $request)
    {
        ScenarioPermission::canCreate();

        $scenario = $this->scenarioService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $scenario,
            'message' => 'messages.scenario.created',
            'status' => 201,
            'resource' => ScenarioResource::class,
        ]);
    }

    public function update(UpdateScenarioRequest $request, int $id)
    {
        ScenarioPermission::canUpdate();

        $scenario = $this->scenarioService->show($id);

        $scenario = $this->scenarioService->update($request->validated(), $scenario);

        return ResponseService::response([
            'success' => true,
            'data' => $scenario,
            'message' => 'messages.scenario.updated',
            'status' => 200,
            'resource' => ScenarioResource::class,
        ]);
    }

    public function delete(int $id)
    {
        ScenarioPermission::canDelete();

        $scenario = $this->scenarioService->show($id);

        $this->scenarioService->delete($scenario);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.scenario.deleted',
            'status' => 200,
        ]);
    }

    public function reorder(int $id, ReOrderScenarioRequest $request)
    {
        ScenarioPermission::canReorder();

        $scenario = $this->scenarioService->show($id);

        $scenario = $this->scenarioService->reorder($scenario, $request->validated());

        return $this->index(request(), 'messages.scenario.reordered');
    }

    public function startAttempt($id)
    {
        ScenarioPermission::canStartAttempt();

        $scenario = $this->scenarioService->show($id);

        $attempt = $this->scenarioService->startAttempt($scenario);

        return ResponseService::response([
            'success' => true,
            'data' => $attempt,
            'message' => 'messages.scenario.attempt_started',
            'status' => 201,
        ]);
    }

    public function getCurrentQuestion(int $id, int $attemptId)
    {
        ScenarioPermission::canStartAttempt();

        $result = $this->scenarioService->getCurrentQuestion($attemptId);

        return ResponseService::response([
            'success' => true,
            'data' => $result,
            'status' => 200,
        ]);
    }

    public function submitAnswer(SubmitScenarioAnswerRequest $request)
    {
        ScenarioPermission::canSubmitAnswer();

        $result = $this->scenarioService->submitAnswer(
            $request->validated()['attempt_id'],
            $request->validated()['question_id'],
            $request->validated()['option_id'] ?? null,
            $request->validated()['answer_text'] ?? null
        );

        return ResponseService::response([
            'success' => true,
            'data' => $result,
            'message' => $result['finished'] ? 'messages.scenario.finished' : 'messages.scenario.answer_submitted',
            'status' => 201,
        ]);
    }

    public function finishAttempt(int $id, int $attemptId)
    {
        ScenarioPermission::canSubmitAnswer();

        $attempt = $this->scenarioService->finishAttempt($attemptId);

        return ResponseService::response([
            'success' => true,
            'data' => $attempt,
            'message' => 'messages.scenario.attempt_finished',
            'status' => 200,
        ]);
    }

    /**
     * Mark description as read for a scenario attempt
     */
    public function markDescriptionRead(int $id, int $attemptId)
    {
        $attempt = $this->scenarioService->markDescriptionRead($attemptId, \App\Models\Users\User::auth()->id);

        return ResponseService::response([
            'success' => true,
            'data' => $attempt,
            'message' => 'messages.description.marked_read',
            'status' => 200,
        ]);
    }
}
