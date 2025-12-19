<?php

namespace Database\Seeders;

use App\Models\Learning\Course;
use App\Models\Learning\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        $courses = Course::query()->get();

        foreach ($courses as $course) {
            for ($i = 1; $i <= 3; $i++) {
                $isPublished = (bool) $course->is_published && $i !== 3 ? true : (bool) ($course->is_published && $i === 3);

                Level::withTrashed()->updateOrCreate(
                    [
                        'course_id' => $course->id,
                        'level_number' => $i,
                    ],
                    [
                        'title' => "المستوى {$i}",
                        'description' => "محتوى تدريبي للمستوى {$i} ضمن دورة: {$course->title}.",
                        'is_published' => $isPublished,
                        'is_free' => $i === 1 ? (bool) $course->is_free : false,
                        'has_certificate' => $i === 3,
                        'order_index' => $i,
                        'deleted_at' => null,
                    ]
                );
            }
        }
    }
}

