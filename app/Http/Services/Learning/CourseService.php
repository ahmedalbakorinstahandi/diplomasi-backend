<?php

namespace App\Http\Services\Learning;

use App\Http\Permissions\Learning\CoursePermission;
use App\Models\Learning\Course;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;

class CourseService
{
    public function index($filters = [])
    {
        $query = Course::query()->with([
            'levels',
            'userCourses',
            'certificates'
        ]);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'id';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = ['title', 'description'];
        $numericFields = [];
        $dateFields = ['created_at'];
        $exactMatchFields = ['is_published', 'is_free'];
        $inFields = [];

        $query = CoursePermission::filterIndex($query);

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
        $course = Course::where('id', $id)->first();
        if (!$course) {
            MessageService::abort(404, 'messages.course.not_found');
        }

        $course->load([
            'levels',
            'userCourses',
            'certificates'
        ]);

        return $course;
    }

    public function create($data)
    {
        $course = Course::create($data);

        $course = $this->show($course->id);

        return $course;
    }

    public function update($data, $course)
    {
        $course->update($data);

        $course = $this->show($course->id);

        return $course;
    }

    public function delete($course)
    {
        // Delete related records if needed
        $course->levels()->delete();
        $course->userCourses()->delete();
        $course->certificates()->delete();

        $course->delete();
    }

    public function reorder($course, $validatedData)
    {
        // Note: Courses don't have sort_order in the model, so reorder might not be applicable
        // If sort_order is needed, it should be added to the model and migration
        return $course;
    }
}

