<?php

namespace Database\Seeders;

use App\Models\Learning\Lesson;
use App\Models\Learning\LessonQuestion;
use Illuminate\Database\Seeder;

class LessonQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $lessons = Lesson::query()->get();

        foreach ($lessons as $lesson) {
            $types = [
                1 => 'single_choice',
                2 => 'true_false',
                3 => $lesson->order_index == 1 ? 'match' : 'multiple_choice',
            ];

            foreach ($types as $orderIndex => $type) {
                LessonQuestion::withTrashed()->updateOrCreate(
                    [
                        'lesson_id' => $lesson->id,
                        'order_index' => $orderIndex,
                    ],
                    [
                        'type' => $type,
                        'question_text' => "سؤال ({$type}) للدرس: {$lesson->title} - رقم {$orderIndex}",
                        'attached_path' => null,
                        'explanation' => 'شرح مختصر يظهر بعد الإجابة لمساعدة المتعلم على الفهم.',
                        'score' => 1,
                        'deleted_at' => null,
                    ]
                );
            }
        }
    }
}

