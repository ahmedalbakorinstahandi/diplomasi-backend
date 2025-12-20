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
            // 'levels',
            // 'userCourses',
            // 'certificates'
        ]);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'order_index';
        $filters['sort_order'] = $filters['sort_order'] ?? 'asc';

        $searchFields = ['title', 'description'];
        $numericFields = ['order_index'];
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
            // 'levels',
            // 'userCourses',
            // 'certificates'
        ]);

        return $course;
    }

    public function create($data)
    {
        $course = Course::create($data);

        OrderHelper::assign($course, 'order_index');

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
        OrderHelper::reorder($course, $validatedData['new_order_index'], 'order_index');

        return $this->show($course->id);
    }
}
