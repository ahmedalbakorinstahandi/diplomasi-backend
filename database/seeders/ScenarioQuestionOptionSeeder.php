<?php

namespace Database\Seeders;

use App\Models\Scenarios\ScenarioQuestion;
use App\Models\Scenarios\ScenarioQuestionOption;
use Illuminate\Database\Seeder;

class ScenarioQuestionOptionSeeder extends Seeder
{
    public function run(): void
    {
        $questionsByScenario = ScenarioQuestion::query()
            ->orderBy('scenario_id')
            ->orderBy('order_index')
            ->get()
            ->groupBy('scenario_id');

        foreach ($questionsByScenario as $scenarioId => $questions) {
            $questions = $questions->values();

            foreach ($questions as $idx => $question) {
                $nextQuestion = $questions->get($idx + 1);
                $nextId = $nextQuestion?->id;

                if ($question->type === 'true_false') {
                    $options = [
                        ['text' => 'صح', 'next_question_id' => $nextId],
                        ['text' => 'خطأ', 'next_question_id' => $nextId],
                    ];
                } else {
                    $options = [
                        ['text' => 'الخيار 1', 'next_question_id' => $nextId],
                        ['text' => 'الخيار 2', 'next_question_id' => $nextId],
                        ['text' => 'الخيار 3', 'next_question_id' => $nextId],
                    ];
                }

                foreach ($options as $oIdx => $opt) {
                    ScenarioQuestionOption::withTrashed()->updateOrCreate(
                        [
                            'question_id' => $question->id,
                            'order_index' => $oIdx + 1,
                        ],
                        [
                            'option_text' => $opt['text'],
                            'next_question_id' => $opt['next_question_id'],
                            'attached_path' => null,
                            'deleted_at' => null,
                        ]
                    );
                }
            }
        }
    }
}

