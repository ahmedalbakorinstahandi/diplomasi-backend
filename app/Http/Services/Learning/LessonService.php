<?php

namespace App\Http\Services\Learning;

use App\Http\Permissions\Learning\LessonPermission;
use App\Models\Learning\Lesson;
use App\Models\Learning\LevelTrack;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;

class LessonService
{
    public function index($filters = [])
    {
        $query = Lesson::query()->with([
            'level',
            // 'lessonQuestions',
            // 'lessonSummary',
            // 'userLessonProgress',
            // 'userLessonAttempts'
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
            // 'lessonQuestions',
            // 'lessonSummary',
            // 'userLessonProgress',
            // 'userLessonAttempts'
        ]);

        return $lesson;
    }

    public function create($data)
    {
        $lesson = Lesson::create($data);

        OrderHelper::assign($lesson, 'order_index');

        // Create or update LevelTrack
        $this->syncLevelTrack($lesson);

        $lesson = $this->show($lesson->id);

        return $lesson;
    }

    public function update($data, $lesson)
    {
        $oldLevelId = $lesson->level_id;

        $lesson->update($data);

        // If level_id changed, update LevelTrack
        if (isset($data['level_id']) && $data['level_id'] != $oldLevelId) {
            // Delete old LevelTrack
            $oldLevelTrack = LevelTrack::where('trackable_id', $lesson->id)
                ->where('trackable_type', Lesson::class)
                ->first();
            if ($oldLevelTrack) {
                $oldLevelTrack->delete();
            }

            // Create new LevelTrack for new level
            $this->syncLevelTrack($lesson);
        } else {
            // Just sync to ensure it exists
            $this->syncLevelTrack($lesson);
        }

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

        // Delete LevelTrack
        $levelTrack = LevelTrack::where('trackable_id', $lesson->id)
            ->where('trackable_type', Lesson::class)
            ->first();
        if ($levelTrack) {
            $levelTrack->delete();
        }

        $lesson->delete();
    }

    public function reorder($lesson, $validatedData)
    {
        OrderHelper::reorder($lesson, $validatedData['new_order_index'], 'order_index');

        return $lesson;
    }

    /**
     * Sync LevelTrack for a lesson
     */
    private function syncLevelTrack(Lesson $lesson)
    {
        $levelTrack = LevelTrack::withTrashed()->updateOrCreate(
            [
                'level_id' => $lesson->level_id,
                'trackable_id' => $lesson->id,
                'trackable_type' => Lesson::class,
            ],
            [
                'deleted_at' => null,
            ]
        );

        if ($levelTrack->wasRecentlyCreated || $levelTrack->order_index === null) {
            OrderHelper::assign($levelTrack, 'order_index');
        }
    }
}
