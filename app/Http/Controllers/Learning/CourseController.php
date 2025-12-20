<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Learning\CoursePermission;
use App\Http\Requests\Learning\CreateCourseRequest;
use App\Http\Requests\Learning\ReOrderCourseRequest;
use App\Http\Requests\Learning\UpdateCourseRequest;
use App\Http\Resources\Learning\CourseResource;
use App\Http\Services\Learning\CourseService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    protected $courseService;

    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }

    public function index(Request $request, $message = null)
    {
        CoursePermission::canView();

        $courses = $this->courseService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $courses,
            'meta' => true,
            'resource' => CourseResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        CoursePermission::canView();

        $course = $this->courseService->show($id);
        CoursePermission::canShow($course);

        return ResponseService::response([
            'success' => true,
            'data' => $course,
            'resource' => CourseResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateCourseRequest $request)
    {
        CoursePermission::canCreate();

        $course = $this->courseService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $course,
            'message' => 'messages.course.created',
            'status' => 201,
            'resource' => CourseResource::class,
        ]);
    }

    public function update(UpdateCourseRequest $request, int $id)
    {
        CoursePermission::canUpdate();

        $course = $this->courseService->show($id);

        $course = $this->courseService->update($request->validated(), $course);

        return ResponseService::response([
            'success' => true,
            'data' => $course,
            'message' => 'messages.course.updated',
            'status' => 200,
            'resource' => CourseResource::class,
        ]);
    }

    public function delete(int $id)
    {
        CoursePermission::canDelete();

        $course = $this->courseService->show($id);

        $this->courseService->delete($course);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.course.deleted',
            'status' => 200,
        ]);
    }

    public function reorder(int $id, ReOrderCourseRequest $request)
    {
        CoursePermission::canReorder();

        $course = $this->courseService->show($id);

        $course = $this->courseService->reorder($course, $request->validated());

        return $this->index(request(), 'messages.course.reordered');
    }
}
