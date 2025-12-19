<?php

namespace Database\Seeders;

use App\Models\Progress\UserCourse;
use App\Models\Scenarios\UserScenarioAnswerOption;
use App\Models\Scenarios\UserScenarioAttempt;
use App\Models\Scenarios\UserScenarioQuestionAnswer;
use Illuminate\Database\Seeder;

class UserScenarioAttemptSeeder extends Seeder
{
    public function run(): void
    {
        $userCourses = UserCourse::query()->with(['course.levels.scenarios.scenarioQuestions.scenarioQuestionOptions'])->get();

        foreach ($userCourses as $userCourse) {
            $level = $userCourse->course?->levels?->sortBy('level_number')->first();
            if (!$level) {
                continue;
            }

            $scenario = $level->scenarios()->orderBy('order_index')->first();
            if (!$scenario) {
                continue;
            }

            $attempt = UserScenarioAttempt::withTrashed()->updateOrCreate(
                [
                    'user_id' => $userCourse->user_id,
                    'scenario_id' => $scenario->id,
                ],
                [
                    'status' => $userCourse->status === 'completed' ? 'finished' : 'in_progress',
                    'started_at' => now()->subDays(2),
                    'finished_at' => $userCourse->status === 'completed' ? now()->subDays(1) : null,
                    'deleted_at' => null,
                ]
            );

            $questions = $scenario->scenarioQuestions()->orderBy('order_index')->get();
            foreach ($questions as $qIdx => $question) {
                $userAnswer = UserScenarioQuestionAnswer::withTrashed()->updateOrCreate(
                    [
                        'attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                    ],
                    [
                        'step_index' => $qIdx + 1,
                        'answered_at' => now()->subDays(1),
                        'time_spent' => 15,
                        'deleted_at' => null,
                    ]
                );

                $opt = $question->scenarioQuestionOptions()->orderBy('order_index')->first();
                if ($opt) {
                    UserScenarioAnswerOption::withTrashed()->updateOrCreate(
                        [
                            'user_answer_id' => $userAnswer->id,
                            'option_id' => $opt->id,
                        ],
                        [
                            'deleted_at' => null,
                        ]
                    );
                }
            }
        }
    }
}

