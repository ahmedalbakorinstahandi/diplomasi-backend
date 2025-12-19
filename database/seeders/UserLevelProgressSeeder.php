<?php

namespace Database\Seeders;

use App\Models\Learning\Level;
use App\Models\Progress\UserCourse;
use App\Models\Progress\UserLevelProgress;
use Illuminate\Database\Seeder;

class UserLevelProgressSeeder extends Seeder
{
    public function run(): void
    {
        $userCourses = UserCourse::query()->with(['course.levels.lessons'])->get();

        foreach ($userCourses as $userCourse) {
            $levels = $userCourse->course?->levels?->sortBy('level_number')->values();
            if (!$levels || $levels->isEmpty()) {
                continue;
            }

            foreach ($levels as $idx => $level) {
                /** @var Level $level */
                $firstLesson = $level->lessons()->orderBy('order_index')->first();

                $status = 'not_started';
                $score = 0;

                if ($userCourse->status === 'completed') {
                    $status = 'completed';
                    $score = 90 + ($idx * 2);
                } elseif ($idx === 0) {
                    $status = 'in_progress';
                    $score = 35;
                }

                UserLevelProgress::withTrashed()->updateOrCreate(
                    [
                        'user_id' => $userCourse->user_id,
                        'level_id' => $level->id,
                    ],
                    [
                        'current_lesson_id' => $status === 'in_progress' ? ($firstLesson?->id) : null,
                        'status' => $status,
                        'score' => $score,
                        'started_at' => $status !== 'not_started' ? now()->subDays(5) : null,
                        'completed_at' => $status === 'completed' ? now()->subDays(1) : null,
                        'deleted_at' => null,
                    ]
                );
            }
        }
    }
}

