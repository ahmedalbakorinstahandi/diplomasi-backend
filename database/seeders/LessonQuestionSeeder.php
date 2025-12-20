<?php

namespace Database\Seeders;

use App\Models\Learning\Lesson;
use App\Models\Learning\LessonQuestion;
use App\Services\OrderHelper;
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
                $questionText = "سؤال ({$type}) للدرس: {$lesson->title} - رقم {$orderIndex}";

                $question = LessonQuestion::withTrashed()->updateOrCreate(
                    [
                        'lesson_id' => $lesson->id,
                        'question_text' => $questionText,
                    ],
                    [
                        'type' => $type,
                        'question_text' => $questionText,
                        'attached_path' => null,
                        'explanation' => 'شرح مختصر يظهر بعد الإجابة لمساعدة المتعلم على الفهم.',
                        'score' => 1,
                        'deleted_at' => null,
                    ]
                );

                if ($question->wasRecentlyCreated || $question->order_index === null) {
                    OrderHelper::assign($question, 'order_index');
                }
            }
        }
    }
}

