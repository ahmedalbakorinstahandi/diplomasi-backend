<?php

namespace Database\Seeders;

use App\Models\Billing\Subscription;
use App\Models\Learning\Course;
use App\Models\Progress\UserCourse;
use App\Models\Users\User;
use Illuminate\Database\Seeder;

class UserCourseSeeder extends Seeder
{
    public function run(): void
    {
        $courses = Course::query()->orderBy('id')->get()->values();
        if ($courses->isEmpty()) {
            return;
        }

        // Users with subscriptions: assign 2 courses per user.
        $subscriptions = Subscription::query()->with('user')->get();
        foreach ($subscriptions as $idx => $subscription) {
            $userId = $subscription->user_id;
            $base = ($idx * 2) % $courses->count();

            for ($k = 0; $k < 2; $k++) {
                $course = $courses[($base + $k) % $courses->count()];

                $startedAt = now()->subDays(7 + $k);
                $status = $k === 0 ? 'active' : 'completed';

                UserCourse::withTrashed()->updateOrCreate(
                    [
                        'user_id' => $userId,
                        'course_id' => $course->id,
                    ],
                    [
                        'subscription_id' => $subscription->id,
                        'status' => $status,
                        'started_at' => $startedAt,
                        'completed_at' => $status === 'completed' ? now()->subDays(1) : null,
                        'deleted_at' => null,
                    ]
                );
            }
        }

        // Some users without subscriptions: assign 1 free course.
        $freeCourse = Course::query()->where('is_free', true)->orderBy('id')->first();
        if (!$freeCourse) {
            return;
        }

        $users = User::query()
            ->where('email', 'like', 'user%@demo.test')
            ->orderBy('id')
            ->skip(20)
            ->take(10)
            ->get();

        foreach ($users as $u) {
            UserCourse::withTrashed()->updateOrCreate(
                ['user_id' => $u->id, 'course_id' => $freeCourse->id],
                [
                    'subscription_id' => null,
                    'status' => 'active',
                    'started_at' => now()->subDays(3),
                    'completed_at' => null,
                    'deleted_at' => null,
                ]
            );
        }
    }
}

