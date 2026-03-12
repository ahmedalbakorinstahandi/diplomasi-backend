<?php

namespace App\Http\Controllers\Scenarios;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Scenarios\ScenarioPermission;
use App\Http\Requests\Scenarios\CreateScenarioMinimalRequest;
use App\Http\Requests\Scenarios\CreateScenarioRequest;
use App\Http\Requests\Scenarios\ReOrderScenarioRequest;
use App\Http\Requests\Scenarios\StartScenarioAttemptRequest;
use App\Http\Requests\Scenarios\SubmitScenarioAnswerRequest;
use App\Http\Requests\Scenarios\UpdateScenarioRequest;
use App\Http\Resources\Scenarios\ScenarioResource;
use App\Http\Requests\Scenarios\ImportScenarioContentRequest;
use App\Http\Requests\Scenarios\ImportScenarioFullRequest;
use App\Http\Services\Scenarios\ScenarioQuestionAdminService;
use App\Http\Services\Scenarios\ScenarioService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class ScenarioController extends Controller
{
    protected $scenarioService;

    protected $scenarioQuestionAdminService;

    public function __construct(ScenarioService $scenarioService, ScenarioQuestionAdminService $scenarioQuestionAdminService)
    {
        $this->scenarioService = $scenarioService;
        $this->scenarioQuestionAdminService = $scenarioQuestionAdminService;
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

    /**
     * إنشاء سيناريو (نسخة كاملة: عنوان، وصف، level_id، is_free).
     */
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

    /**
     * إنشاء سيناريو (نسخة مختصرة: level_id + title فقط، الباقي قيم افتراضية).
     */
    public function createMinimal(CreateScenarioMinimalRequest $request)
    {
        ScenarioPermission::canCreate();

        $data = array_merge($request->validated(), [
            'description' => null,
            'is_free' => false,
        ]);
        $scenario = $this->scenarioService->create($data);

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

    /**
     * List user attempts for a scenario.
     */
    public function listAttempts(int $id)
    {
        ScenarioPermission::canStartAttempt();

        $attempts = $this->scenarioService->getAttemptsForScenario($id);

        return ResponseService::response([
            'success' => true,
            'data' => $attempts,
            'status' => 200,
        ]);
    }

    /**
     * Get full journey for one scenario attempt.
     */
    public function attemptJourney(int $id, int $attemptId)
    {
        ScenarioPermission::canStartAttempt();

        $journey = $this->scenarioService->getAttemptJourney($id, $attemptId);

        return ResponseService::response([
            'success' => true,
            'data' => $journey,
            'status' => 200,
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
     * استيراد كامل: إنشاء سيناريو جديد + استيراد الشاشات والخيارات في طلب واحد.
     * Body: level_id, title, description?, is_free?, screens (نفس هيكل import-content).
     */
    public function importFull(ImportScenarioFullRequest $request)
    {
        ScenarioPermission::canCreate();

        $validated = $request->validated();
        $scenarioData = [
            'level_id' => $validated['level_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'is_free' => isset($validated['is_free']) ? (bool) $validated['is_free'] : false,
        ];
        $scenario = $this->scenarioService->create($scenarioData);

        $importPayload = [
            'replace' => true,
            'screens' => $validated['screens'],
        ];
        $result = $this->scenarioQuestionAdminService->importContent($scenario->id, $importPayload);

        return ResponseService::response([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'created_questions' => $result['created_questions'],
                'scenario' => new ScenarioResource($result['scenario']),
            ],
            'status' => 201,
        ]);
    }

    /**
     * استيراد محتوى السيناريو فقط (شاشات + خيارات) لسيناريو موجود.
     * انظر التوثيق في docs/SCENARIO_IMPORT.md
     */
    public function importContent(ImportScenarioContentRequest $request, int $id)
    {
        ScenarioPermission::canUpdate();

        $scenario = $this->scenarioService->show($id);
        ScenarioPermission::canShow($scenario);

        $result = $this->scenarioQuestionAdminService->importContent($id, $request->validated());

        return ResponseService::response([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'created_questions' => $result['created_questions'],
                'scenario' => new ScenarioResource($result['scenario']),
            ],
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
