<?php

namespace App\Http\Controllers\Progress;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Progress\ProgressPermission;
use App\Http\Requests\Progress\CreateProgressRequest;
use App\Http\Requests\Progress\UpdateProgressRequest;
use App\Http\Resources\Progress\UserCourseResource;
use App\Http\Resources\Progress\UserLessonProgressResource;
use App\Http\Resources\Progress\UserLevelProgressResource;
use App\Http\Services\Progress\ProgressService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    protected $progressService;

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    public function index(Request $request, $type = 'course')
    {
        ProgressPermission::canView();

        $progress = $this->progressService->index($request->all(), $type);

        $resource = match($type) {
            'course' => UserCourseResource::class,
            'lesson' => UserLessonProgressResource::class,
            'level' => UserLevelProgressResource::class,
            default => UserCourseResource::class,
        };

        return ResponseService::response([
            'success' => true,
            'data' => $progress,
            'meta' => true,
            'resource' => $resource,
            'status' => 200,
        ]);
    }

    public function show(int $id, $type = 'course')
    {
        ProgressPermission::canView();

        $progress = $this->progressService->show($id, $type);
        ProgressPermission::canShow($progress);

        $resource = match($type) {
            'course' => UserCourseResource::class,
            'lesson' => UserLessonProgressResource::class,
            'level' => UserLevelProgressResource::class,
            default => UserCourseResource::class,
        };

        return ResponseService::response([
            'success' => true,
            'data' => $progress,
            'resource' => $resource,
            'status' => 200,
        ]);
    }

    public function create(CreateProgressRequest $request, $type = 'course')
    {
        ProgressPermission::canCreate();

        $progress = $this->progressService->create($request->validated(), $type);

        $resource = match($type) {
            'course' => UserCourseResource::class,
            'lesson' => UserLessonProgressResource::class,
            'level' => UserLevelProgressResource::class,
            default => UserCourseResource::class,
        };

        return ResponseService::response([
            'success' => true,
            'data' => $progress,
            'message' => 'messages.progress.created',
            'status' => 201,
            'resource' => $resource,
        ]);
    }

    public function update(UpdateProgressRequest $request, int $id, $type = 'course')
    {
        ProgressPermission::canUpdate();

        $progress = $this->progressService->show($id, $type);
        ProgressPermission::canShow($progress);

        $progress = $this->progressService->update($request->validated(), $progress, $type);

        $resource = match($type) {
            'course' => UserCourseResource::class,
            'lesson' => UserLessonProgressResource::class,
            'level' => UserLevelProgressResource::class,
            default => UserCourseResource::class,
        };

        return ResponseService::response([
            'success' => true,
            'data' => $progress,
            'message' => 'messages.progress.updated',
            'status' => 200,
            'resource' => $resource,
        ]);
    }
}

