<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Learning\LessonPermission;
use App\Http\Requests\Learning\CreateLessonRequest;
use App\Http\Requests\Learning\ReOrderLessonRequest;
use App\Http\Requests\Learning\UpdateLessonRequest;
use App\Http\Resources\Learning\LessonResource;
use App\Http\Services\Learning\LessonService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    protected $lessonService;

    public function __construct(LessonService $lessonService)
    {
        $this->lessonService = $lessonService;
    }

    public function index(Request $request)
    {
        LessonPermission::canView();

        $lessons = $this->lessonService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'data' => $lessons,
            'meta' => true,
            'resource' => LessonResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        LessonPermission::canView();

        $lesson = $this->lessonService->show($id);
        LessonPermission::canShow($lesson);

        return ResponseService::response([
            'success' => true,
            'data' => $lesson,
            'resource' => LessonResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateLessonRequest $request)
    {
        LessonPermission::canCreate();

        $lesson = $this->lessonService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $lesson,
            'message' => 'messages.lesson.created',
            'status' => 201,
            'resource' => LessonResource::class,
        ]);
    }

    public function update(UpdateLessonRequest $request, int $id)
    {
        LessonPermission::canUpdate();

        $lesson = $this->lessonService->show($id);

        $lesson = $this->lessonService->update($request->validated(), $lesson);

        return ResponseService::response([
            'success' => true,
            'data' => $lesson,
            'message' => 'messages.lesson.updated',
            'status' => 200,
            'resource' => LessonResource::class,
        ]);
    }

    public function delete(int $id)
    {
        LessonPermission::canDelete();

        $lesson = $this->lessonService->show($id);

        $this->lessonService->delete($lesson);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.lesson.deleted',
            'status' => 200,
        ]);
    }

    public function reorder(int $id, ReOrderLessonRequest $request)
    {
        LessonPermission::canReorder();

        $lesson = $this->lessonService->show($id);

        $lesson = $this->lessonService->reorder($lesson, $request->validated());

        return $this->index(request());
    }
}

