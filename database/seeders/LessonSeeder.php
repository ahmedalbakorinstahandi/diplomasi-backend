<?php

namespace Database\Seeders;

use App\Models\Learning\Lesson;
use App\Models\Learning\Level;
use App\Services\OrderHelper;
use Illuminate\Database\Seeder;

class LessonSeeder extends Seeder
{
    public function run(): void
    {
        $levels = Level::query()->get();

        $videoUrls = [
            'https://www.youtube.com/watch?v=5MgBikgcWnY',
            'https://www.youtube.com/watch?v=Y2VF8tmLFHw',
            'https://www.youtube.com/watch?v=OQn9yJb9G0w',
            'https://www.youtube.com/watch?v=2Vv-BfVoq4g',
            'https://www.youtube.com/watch?v=kJQP7kiw5Fk',
            'https://www.youtube.com/watch?v=fLexgOxsZu0',
        ];

        foreach ($levels as $level) {
            for ($i = 1; $i <= 6; $i++) {
                $lessonNumber = (string) $i;

                $lesson = Lesson::withTrashed()->updateOrCreate(
                    [
                        'level_id' => $level->id,
                        'lesson_number' => $lessonNumber,
                    ],
                    [
                        'title' => "الدرس {$i}",
                        'description' => "شرح وتمارين للدرس {$i} ضمن {$level->title}.",
                        'video_url' => $videoUrls[($i - 1) % count($videoUrls)],
                        'content' => "هذا محتوى تدريبي تجريبي للدرس {$i}. يتضمن نقاطاً مختصرة وأمثلة تطبيقية وأسئلة للمراجعة.",
                        'is_published' => (bool) $level->is_published && $i !== 6,
                        'deleted_at' => null,
                    ]
                );

                if ($lesson->wasRecentlyCreated || $lesson->order_index === null) {
                    OrderHelper::assign($lesson, 'order_index');
                }
            }
        }
    }
}

