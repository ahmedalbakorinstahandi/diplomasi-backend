<?php

namespace Database\Seeders;

use App\Models\Learning\LessonQuestion;
use App\Models\Learning\LessonQuestionOption;
use App\Services\OrderHelper;
use Illuminate\Database\Seeder;

class LessonQuestionOptionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = LessonQuestion::query()->get();

        foreach ($questions as $question) {
            if ($question->type === 'true_false') {
                $options = [
                    ['text' => 'صح', 'is_correct' => 1],
                    ['text' => 'خطأ', 'is_correct' => 0],
                ];

                foreach ($options as $opt) {
                    $option = LessonQuestionOption::withTrashed()->updateOrCreate(
                        [
                            'question_id' => $question->id,
                            'option_text' => $opt['text'],
                        ],
                        [
                            'pair_key' => null,
                            'is_correct' => $opt['is_correct'],
                            'attached_path' => null,
                            'deleted_at' => null,
                        ]
                    );

                    if ($option->wasRecentlyCreated || $option->order_index === null) {
                        OrderHelper::assign($option, 'order_index');
                    }
                }

                continue;
            }

            if ($question->type === 'match') {
                $options = [
                    ['text' => 'مصطلح 1', 'pair_key' => 'L1', 'is_correct' => null],
                    ['text' => 'مصطلح 2', 'pair_key' => 'L2', 'is_correct' => null],
                    ['text' => 'تعريف 1', 'pair_key' => null, 'is_correct' => null],
                    ['text' => 'تعريف 2', 'pair_key' => null, 'is_correct' => null],
                ];

                foreach ($options as $opt) {
                    $option = LessonQuestionOption::withTrashed()->updateOrCreate(
                        [
                            'question_id' => $question->id,
                            'option_text' => $opt['text'],
                        ],
                        [
                            'pair_key' => $opt['pair_key'],
                            'is_correct' => $opt['is_correct'],
                            'attached_path' => null,
                            'deleted_at' => null,
                        ]
                    );

                    if ($option->wasRecentlyCreated || $option->order_index === null) {
                        OrderHelper::assign($option, 'order_index');
                    }
                }

                continue;
            }

            // single_choice / multiple_choice
            $options = [
                ['text' => 'خيار (أ)', 'is_correct' => 0],
                ['text' => 'خيار (ب)', 'is_correct' => $question->type === 'single_choice' || $question->type === 'multiple_choice' ? 1 : 0],
                ['text' => 'خيار (ج)', 'is_correct' => 0],
                ['text' => 'خيار (د)', 'is_correct' => $question->type === 'multiple_choice' ? 1 : 0],
            ];

            foreach ($options as $opt) {
                $option = LessonQuestionOption::withTrashed()->updateOrCreate(
                    [
                        'question_id' => $question->id,
                        'option_text' => $opt['text'],
                    ],
                    [
                        'pair_key' => null,
                        'is_correct' => $opt['is_correct'],
                        'attached_path' => null,
                        'deleted_at' => null,
                    ]
                );

                if ($option->wasRecentlyCreated || $option->order_index === null) {
                    OrderHelper::assign($option, 'order_index');
                }
            }
        }
    }
}

