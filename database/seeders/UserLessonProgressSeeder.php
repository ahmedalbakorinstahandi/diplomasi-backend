<?php

namespace Database\Seeders;

use App\Models\Progress\UserCourse;
use App\Models\Progress\UserLessonProgress;
use Illuminate\Database\Seeder;

class UserLessonProgressSeeder extends Seeder
{
    public function run(): void
    {
        $userCourses = UserCourse::query()->with(['course.levels.lessons'])->get();

        foreach ($userCourses as $userCourse) {
            $levels = $userCourse->course?->levels?->sortBy('level_number')->values();
            if (!$levels || $levels->isEmpty()) {
                continue;
            }

            // Only seed first level lessons to keep dataset medium.
            $level = $levels->first();
            $lessons = $level->lessons()->orderBy('order_index')->get();
            if ($lessons->isEmpty()) {
                continue;
            }

            foreach ($lessons->take(3) as $idx => $lesson) {
                $status = $idx < 2 ? 'completed' : 'in_progress';
                $score = $idx < 2 ? 85 + ($idx * 5) : 40;

                if ($userCourse->status === 'completed') {
                    $status = 'completed';
                    $score = 90 + ($idx * 2);
                }

                UserLessonProgress::withTrashed()->updateOrCreate(
                    [
                        'user_id' => $userCourse->user_id,
                        'lesson_id' => $lesson->id,
                    ],
                    [
                        'status' => $status,
                        'score' => $score,
                        'started_at' => now()->subDays(4),
                        'completed_at' => $status === 'completed' ? now()->subDays(2) : null,
                        'deleted_at' => null,
                    ]
                );
            }
        }
    }
}

