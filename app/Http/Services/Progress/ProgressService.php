<?php

namespace App\Http\Services\Progress;

use App\Events\UserCourseCompleted;
use App\Events\UserLevelCompleted;
use App\Http\Permissions\Progress\ProgressPermission;
use App\Models\Progress\UserCourse;
use App\Models\Progress\UserLessonProgress;
use App\Models\Progress\UserLevelProgress;
use App\Services\FilterService;
use App\Services\MessageService;
use Illuminate\Support\Facades\Event;

class ProgressService
{
    public function index($filters = [], $type = 'course')
    {
        $query = match($type) {
            'course' => UserCourse::query()->with(['user', 'course', 'subscription']),
            'lesson' => UserLessonProgress::query()->with(['user', 'lesson']),
            'level' => UserLevelProgress::query()->with(['user', 'level', 'currentLesson']),
            default => UserCourse::query()->with(['user', 'course', 'subscription']),
        };

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'id';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = [];
        $numericFields = ['score'];
        $dateFields = ['started_at', 'completed_at', 'created_at'];
        $exactMatchFields = ['user_id', 'status'];
        $inFields = [];

        if ($type === 'course') {
            $exactMatchFields[] = 'course_id';
            $exactMatchFields[] = 'subscription_id';
        } elseif ($type === 'lesson') {
            $exactMatchFields[] = 'lesson_id';
        } elseif ($type === 'level') {
            $exactMatchFields[] = 'level_id';
            $exactMatchFields[] = 'current_lesson_id';
        }

        $query = ProgressPermission::filterIndex($query);

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

    public function show(int $id, $type = 'course')
    {
        $progress = match($type) {
            'course' => UserCourse::where('id', $id)->first(),
            'lesson' => UserLessonProgress::where('id', $id)->first(),
            'level' => UserLevelProgress::where('id', $id)->first(),
            default => UserCourse::where('id', $id)->first(),
        };

        if (!$progress) {
            MessageService::abort(404, 'messages.progress.not_found');
        }

        if ($type === 'course') {
            $progress->load(['user', 'course', 'subscription']);
        } elseif ($type === 'lesson') {
            $progress->load(['user', 'lesson']);
        } elseif ($type === 'level') {
            $progress->load(['user', 'level', 'currentLesson']);
        }

        return $progress;
    }

    public function create($data, $type = 'course')
    {
        $progress = match($type) {
            'course' => UserCourse::create($data),
            'lesson' => UserLessonProgress::create($data),
            'level' => UserLevelProgress::create($data),
            default => UserCourse::create($data),
        };

        return $this->show($progress->id, $type);
    }

    public function update($data, $progress, $type = 'course')
    {
        $oldStatus = $progress->status;
        $progress->update($data);
        $progress->refresh();

        // إطلاق Events عند إكمال المستوى أو الكورس
        if ($type === 'level' && $progress instanceof UserLevelProgress) {
            if (isset($data['status']) && $data['status'] === 'completed' && $oldStatus !== 'completed') {
                Event::dispatch(new UserLevelCompleted($progress));
            }
        } elseif ($type === 'course' && $progress instanceof UserCourse) {
            if (isset($data['status']) && $data['status'] === 'completed' && $oldStatus !== 'completed') {
                Event::dispatch(new UserCourseCompleted($progress));
            }
        }

        return $this->show($progress->id, $type);
    }

    public function delete($progress)
    {
        $progress->delete();
    }
}

