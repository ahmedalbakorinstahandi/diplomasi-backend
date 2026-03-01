<?php

namespace App\Http\Controllers\Scenarios;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Scenarios\ScenarioQuestionPermission;
use App\Http\Requests\Scenarios\CreateScenarioQuestionRequest;
use App\Http\Requests\Scenarios\ReOrderScenarioQuestionRequest;
use App\Http\Requests\Scenarios\UpdateScenarioQuestionRequest;
use App\Http\Resources\Scenarios\ScenarioQuestionResource;
use App\Http\Services\Scenarios\ScenarioQuestionAdminService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class ScenarioQuestionController extends Controller
{
    protected $scenarioQuestionAdminService;

    public function __construct(ScenarioQuestionAdminService $scenarioQuestionAdminService)
    {
        $this->scenarioQuestionAdminService = $scenarioQuestionAdminService;
    }

    public function index(Request $request, $message = null)
    {
        ScenarioQuestionPermission::canView();

        $questions = $this->scenarioQuestionAdminService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $questions,
            'meta' => true,
            'resource' => ScenarioQuestionResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        ScenarioQuestionPermission::canView();

        $question = $this->scenarioQuestionAdminService->show($id);

        return ResponseService::response([
            'success' => true,
            'data' => $question,
            'resource' => ScenarioQuestionResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateScenarioQuestionRequest $request)
    {
        ScenarioQuestionPermission::canCreate();

        $question = $this->scenarioQuestionAdminService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $question,
            'message' => 'messages.scenario_question.created',
            'status' => 201,
            'resource' => ScenarioQuestionResource::class,
        ]);
    }

    public function update(UpdateScenarioQuestionRequest $request, int $id)
    {
        ScenarioQuestionPermission::canUpdate();

        $question = $this->scenarioQuestionAdminService->show($id);

        $question = $this->scenarioQuestionAdminService->update($request->validated(), $question);

        return ResponseService::response([
            'success' => true,
            'data' => $question,
            'message' => 'messages.scenario_question.updated',
            'status' => 200,
            'resource' => ScenarioQuestionResource::class,
        ]);
    }

    public function delete(int $id)
    {
        ScenarioQuestionPermission::canDelete();

        $question = $this->scenarioQuestionAdminService->show($id);

        $this->scenarioQuestionAdminService->delete($question);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.scenario_question.deleted',
            'status' => 200,
        ]);
    }

    public function reorder(int $id, ReOrderScenarioQuestionRequest $request)
    {
        ScenarioQuestionPermission::canReorder();

        $question = $this->scenarioQuestionAdminService->show($id);

        $question = $this->scenarioQuestionAdminService->reorder($question, $request->validated());

        return $this->index(request(), 'messages.scenario_question.reordered');
    }

    public function validateFlow(Request $request)
    {
        ScenarioQuestionPermission::canUpdate();

        $scenarioId = (int) $request->query('scenario_id');
        if (!$scenarioId) {
            return ResponseService::response([
                'success' => false,
                'message' => 'scenario_id is required',
                'status' => 422,
            ]);
        }

        $strict = filter_var($request->query('strict', true), FILTER_VALIDATE_BOOL);
        $result = $this->scenarioQuestionAdminService->validateFlow($scenarioId, $strict);

        return ResponseService::response([
            'success' => true,
            'data' => $result,
            'status' => 200,
        ]);
    }
}
