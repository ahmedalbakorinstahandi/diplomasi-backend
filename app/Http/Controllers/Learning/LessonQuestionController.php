<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Learning\LessonQuestionPermission;
use App\Http\Requests\Learning\CreateLessonQuestionRequest;
use App\Http\Requests\Learning\ReOrderLessonQuestionRequest;
use App\Http\Requests\Learning\UpdateLessonQuestionRequest;
use App\Http\Resources\Learning\LessonQuestionResource;
use App\Http\Services\Learning\LessonQuestionAdminService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class LessonQuestionController extends Controller
{
    protected $lessonQuestionAdminService;

    public function __construct(LessonQuestionAdminService $lessonQuestionAdminService)
    {
        $this->lessonQuestionAdminService = $lessonQuestionAdminService;
    }

    public function index(Request $request, $message = null)
    {
        LessonQuestionPermission::canView();

        $questions = $this->lessonQuestionAdminService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $questions,
            'meta' => true,
            'resource' => LessonQuestionResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        LessonQuestionPermission::canView();

        $question = $this->lessonQuestionAdminService->show($id);

        return ResponseService::response([
            'success' => true,
            'data' => $question,
            'resource' => LessonQuestionResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateLessonQuestionRequest $request)
    {
        LessonQuestionPermission::canCreate();

        $question = $this->lessonQuestionAdminService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $question,
            'message' => 'messages.lesson_question.created',
            'status' => 201,
            'resource' => LessonQuestionResource::class,
        ]);
    }

    public function update(UpdateLessonQuestionRequest $request, int $id)
    {
        LessonQuestionPermission::canUpdate();

        $question = $this->lessonQuestionAdminService->show($id);

        $question = $this->lessonQuestionAdminService->update($request->validated(), $question);

        return ResponseService::response([
            'success' => true,
            'data' => $question,
            'message' => 'messages.lesson_question.updated',
            'status' => 200,
            'resource' => LessonQuestionResource::class,
        ]);
    }

    public function delete(int $id)
    {
        LessonQuestionPermission::canDelete();

        $question = $this->lessonQuestionAdminService->show($id);

        $this->lessonQuestionAdminService->delete($question);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.lesson_question.deleted',
            'status' => 200,
        ]);
    }

    public function reorder(int $id, ReOrderLessonQuestionRequest $request)
    {
        LessonQuestionPermission::canReorder();

        $question = $this->lessonQuestionAdminService->show($id);

        $question = $this->lessonQuestionAdminService->reorder($question, $request->validated());

        return $this->index(request(), 'messages.lesson_question.reordered');
    }
}
