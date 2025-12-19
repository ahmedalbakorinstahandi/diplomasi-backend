<?php

namespace Database\Seeders;

use App\Models\Scenarios\Scenario;
use App\Models\Scenarios\ScenarioQuestion;
use Illuminate\Database\Seeder;

class ScenarioQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $scenarios = Scenario::query()->get();

        foreach ($scenarios as $scenario) {
            for ($i = 1; $i <= 3; $i++) {
                $type = $i === 2 ? 'true_false' : 'single_choice';

                $q = ScenarioQuestion::withTrashed()->updateOrCreate(
                    [
                        'scenario_id' => $scenario->id,
                        'order_index' => $i,
                    ],
                    [
                        'code' => "S{$scenario->id}Q{$i}",
                        'type' => $type,
                        'question_text' => "سؤال سيناريو ({$type}) رقم {$i} للسيناريو {$scenario->id}",
                        'attached_path' => null,
                        'explanation' => 'شرح مختصر بعد الإجابة لتوضيح السبب.',
                        'deleted_at' => null,
                    ]
                );

                // Ensure scenario has a start question.
                if ($i === 1 && !$scenario->start_question_id) {
                    $scenario->start_question_id = $q->id;
                    $scenario->save();
                }
            }
        }
    }
}

