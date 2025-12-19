<?php

namespace Database\Seeders;

use App\Models\Learning\Lesson;
use App\Models\Progress\UserCourse;
use App\Models\Progress\UserLessonAnswerMatch;
use App\Models\Progress\UserLessonAnswerOption;
use App\Models\Progress\UserLessonAttempt;
use App\Models\Progress\UserLessonQuestionAnswer;
use Illuminate\Database\Seeder;

class UserLessonAttemptSeeder extends Seeder
{
    public function run(): void
    {
        $userCourses = UserCourse::query()->with(['course.levels.lessons'])->get();

        foreach ($userCourses as $userCourse) {
            $level = $userCourse->course?->levels?->sortBy('level_number')->first();
            if (!$level) {
                continue;
            }

            /** @var Lesson|null $lesson */
            $lesson = $level->lessons()->orderBy('order_index')->first();
            if (!$lesson) {
                continue;
            }

            $questions = $lesson->lessonQuestions()->orderBy('order_index')->get();
            if ($questions->isEmpty()) {
                continue;
            }

            $attempt = UserLessonAttempt::withTrashed()->updateOrCreate(
                [
                    'user_id' => $userCourse->user_id,
                    'lesson_id' => $lesson->id,
                ],
                [
                    'status' => $userCourse->status === 'completed' ? 'finished' : 'in_progress',
                    'score' => $userCourse->status === 'completed' ? 95 : 40,
                    'current_question_id' => $userCourse->status === 'completed' ? null : $questions->first()->id,
                    'started_at' => now()->subDays(3),
                    'finished_at' => $userCourse->status === 'completed' ? now()->subDays(2) : null,
                    'total_time' => $userCourse->status === 'completed' ? 420 : 120,
                    'deleted_at' => null,
                ]
            );

            foreach ($questions as $qIdx => $question) {
                $answer = UserLessonQuestionAnswer::withTrashed()->updateOrCreate(
                    [
                        'attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                    ],
                    [
                        'step_index' => $qIdx + 1,
                        'is_correct' => true,
                        'score' => 1,
                        'time_spent' => 20,
                        'answered_at' => now()->subDays(2),
                        'deleted_at' => null,
                    ]
                );

                $options = $question->lessonQuestionOptions()->orderBy('order_index')->get();

                if ($question->type === 'match' && $options->count() >= 4) {
                    // left: 1,2 - right: 3,4 (based on our seeding)
                    UserLessonAnswerMatch::withTrashed()->updateOrCreate(
                        [
                            'user_answer_id' => $answer->id,
                            'left_option_id' => $options[0]->id,
                            'right_option_id' => $options[2]->id,
                        ],
                        ['is_correct' => true, 'deleted_at' => null]
                    );

                    UserLessonAnswerMatch::withTrashed()->updateOrCreate(
                        [
                            'user_answer_id' => $answer->id,
                            'left_option_id' => $options[1]->id,
                            'right_option_id' => $options[3]->id,
                        ],
                        ['is_correct' => true, 'deleted_at' => null]
                    );
                } else {
                    $selected = $options->where('is_correct', true)->values();
                    if ($selected->isEmpty() && $options->isNotEmpty()) {
                        $selected = collect([$options->first()]);
                    }

                    foreach ($selected as $opt) {
                        UserLessonAnswerOption::withTrashed()->updateOrCreate(
                            [
                                'user_answer_id' => $answer->id,
                                'option_id' => $opt->id,
                            ],
                            [
                                'is_correct' => (bool) $opt->is_correct,
                                'deleted_at' => null,
                            ]
                        );
                    }
                }
            }
        }
    }
}

