<?php

namespace App\Http\Services\Learning;

use App\Http\Permissions\Learning\LessonPermission;
use App\Models\Learning\Lesson;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;

class LessonService
{
    public function index($filters = [])
    {
        $query = Lesson::query()->with([
            'level',
            'lessonQuestions',
            'lessonSummary',
            'userLessonProgress',
            'userLessonAttempts'
        ]);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'order_index';
        $filters['sort_order'] = $filters['sort_order'] ?? 'asc';

        $searchFields = ['title', 'description'];
        $numericFields = ['lesson_number', 'order_index'];
        $dateFields = ['created_at'];
        $exactMatchFields = ['is_published', 'level_id'];
        $inFields = [];

        $query = LessonPermission::filterIndex($query);

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
        $lesson = Lesson::where('id', $id)->first();
        if (!$lesson) {
            MessageService::abort(404, 'messages.lesson.not_found');
        }

        $lesson->load([
            'level',
            'lessonQuestions',
            'lessonSummary',
            'userLessonProgress',
            'userLessonAttempts'
        ]);

        return $lesson;
    }

    public function create($data)
    {
        $lesson = Lesson::create($data);

        OrderHelper::assign($lesson, 'order_index');

        $lesson = $this->show($lesson->id);

        return $lesson;
    }

    public function update($data, $lesson)
    {
        $lesson->update($data);

        $lesson = $this->show($lesson->id);

        return $lesson;
    }

    public function delete($lesson)
    {
        // Delete related records if needed
        $lesson->lessonQuestions()->delete();
        $lesson->lessonSummary()->delete();
        $lesson->userLessonProgress()->delete();
        $lesson->userLessonAttempts()->delete();

        $lesson->delete();
    }

    public function reorder($lesson, $validatedData)
    {
        OrderHelper::reorder($lesson, $validatedData['new_order_index'], 'order_index');

        return $lesson;
    }
}
