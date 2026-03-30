<?php

namespace App\Http\Services\Learning;

use App\Models\Learning\Lesson;
use App\Models\Learning\LessonQuestion;
use App\Models\Learning\LessonQuestionOption;
use App\Services\OrderHelper;
use App\Models\Learning\LevelTrack;
use App\Models\Progress\UserLessonAttempt;
use App\Models\Progress\UserLessonQuestionAnswer;
use App\Models\Progress\UserLessonAnswerOption;
use App\Models\Progress\UserLessonAnswerMatch;
use App\Models\Users\User;
use App\Services\MessageService;
use App\Services\TrackProgressService;
use Illuminate\Support\Facades\DB;

class LessonQuestionService
{
    /**
     * استيراد أسئلة الدرس (إنشاء أو استبدال كامل) من JSON.
     *
     * @param int $lessonId
     * @param array $data ['replace' => bool, 'questions' => [...]]
     * @return array ['created_questions' => int]
     */
    public function importQuestions(int $lessonId, array $data): array
    {
        $lesson = Lesson::find($lessonId);
        if (!$lesson) {
            MessageService::abort(404, 'messages.lesson.not_found');
        }

        $replace = $data['replace'] ?? false;
        $questions = $data['questions'] ?? [];

        if (empty($questions)) {
            MessageService::abort(422, 'messages.lesson.import_questions_required');
        }

        DB::beginTransaction();
        try {
            if ($replace) {
                // حذف كل الأسئلة والخيارات القديمة
                LessonQuestion::where('lesson_id', $lessonId)->delete();
            }

            foreach ($questions as $index => $q) {
                $question = LessonQuestion::create([
                    'lesson_id' => $lessonId,
                    'type' => $q['type'],
                    'question_text' => $q['text'],
                    'explanation' => $q['explanation'] ?? null,
                    'score' => $q['score'] ?? null,
                ]);

                OrderHelper::assign($question, 'order_index');

                // إنشاء الخيارات/الأزواج حسب النوع
                $options = $q['options'] ?? [];
                if (in_array($q['type'], ['single_choice', 'multiple_choice', 'true_false'], true)) {
                    foreach ($options as $opt) {
                        $option = LessonQuestionOption::create([
                            'question_id' => $question->id,
                            'option_text' => $opt['text'],
                            'is_correct' => $opt['is_correct'] ?? false,
                        ]);
                        OrderHelper::assign($option, 'order_index');
                    }
                } elseif ($q['type'] === 'match') {
                    // نتوقع عناصر مسطّحة مع pair_key مثل p1, p2, p3 ...
                    foreach ($options as $opt) {
                        $option = LessonQuestionOption::create([
                            'question_id' => $question->id,
                            'option_text' => $opt['text'],
                            'pair_key' => $opt['pair_key'] ?? null,
                            // في المطابقة: نعتبر جميع العناصر جزءاً من الإجابة الصحيحة
                            'is_correct' => true,
                        ]);
                        OrderHelper::assign($option, 'order_index');
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'created_questions' => count($questions),
        ];
    }

    /**
     * بدء محاولة جديدة أو إرجاع المحاولة الحالية
     */
    public function startOrGetAttempt($lessonId, $userId)
    {
        $lesson = Lesson::find($lessonId);
        if (!$lesson) {
            MessageService::abort(404, 'messages.lesson.not_found');
        }

        // Check if lesson is accessible (previous track is completed)
        $levelTrack = LevelTrack::where('trackable_id', $lessonId)
            ->where('trackable_type', Lesson::class)
            ->first();

        if ($levelTrack) {
            $trackProgressService = app(TrackProgressService::class);
            if (!$trackProgressService->canAccessTrack($levelTrack, $userId)) {
                MessageService::abort(403, 'messages.lesson.locked');
            }
        }

        // البحث عن محاولة قائمة
        $attempt = UserLessonAttempt::where('user_id', $userId)
            ->where('lesson_id', $lessonId)
            ->where('status', 'in_progress')
            ->first();

        if ($attempt) {
            return $attempt;
        }

        // جلب أول سؤال
        $firstQuestion = LessonQuestion::where('lesson_id', $lessonId)
            ->orderBy('order_index')
            ->first();

        if (!$firstQuestion) {
            MessageService::abort(404, 'messages.lesson.no_questions');
        }

        // إنشاء محاولة جديدة
        $attempt = UserLessonAttempt::create([
            'user_id' => $userId,
            'lesson_id' => $lessonId,
            'status' => 'in_progress',
            'score' => 0,
            'progress_percentage' => 0,
            'current_question_id' => $firstQuestion->id,
            'started_at' => now(),
        ]);

        // Update progress (initial state)
        $trackProgressService = app(TrackProgressService::class);
        $trackProgressService->calculateAndUpdateLessonProgress($lesson, $userId, $attempt);

        return $attempt;
    }

    /**
     * جلب جميع أسئلة الدرس مع حالاتها
     */
    public function getQuestionsWithStatus($lessonId, $attemptId = null, $userId = null)
    {
        $lesson = Lesson::find($lessonId);
        if (!$lesson) {
            MessageService::abort(404, 'messages.lesson.not_found');
        }

        // جلب جميع الأسئلة مرتبة
        $questions = LessonQuestion::where('lesson_id', $lessonId)
            ->orderBy('order_index')
            ->get();

        $attempt = null;
        $answers = collect();
        $currentQuestionId = null;

        if ($attemptId) {
            $attempt = UserLessonAttempt::find($attemptId);
            if ($attempt) {
                if ($userId !== null && (int) $attempt->user_id !== (int) $userId) {
                    MessageService::abort(403, 'messages.attempt.unauthorized');
                }

                $currentQuestionId = $attempt->current_question_id;
                $answers = UserLessonQuestionAnswer::where('attempt_id', $attemptId)
                    ->with(['userLessonAnswerOptions', 'userLessonAnswerMatches'])
                    ->get()
                    ->keyBy('question_id');
            }
        }

        $questionsWithStatus = $questions->map(function ($question) use ($answers, $currentQuestionId) {
            $userAnswer = $answers->get($question->id);
            
            $status = 'not_answered';
            if ($userAnswer) {
                $status = 'answered';
            } elseif ($question->id == $currentQuestionId) {
                $status = 'current';
            }

            return [
                'id' => $question->id,
                'type' => $question->type,
                'question_text' => $question->question_text,
                'attached_path' => $question->attached_path,
                'order_index' => $question->order_index,
                'status' => $status,
                'user_answer' => $userAnswer ? [
                    'is_correct' => $userAnswer->is_correct,
                    'score' => $userAnswer->score,
                    'answered_at' => $userAnswer->answered_at,
                ] : null,
            ];
        });

        $answeredCount = $questionsWithStatus->where('status', 'answered')->count();
        $totalCount = $questionsWithStatus->count();

        return [
            'questions' => $questionsWithStatus,
            'progress' => [
                'answered' => $answeredCount,
                'total' => $totalCount,
                'percentage' => $totalCount > 0 ? round(($answeredCount / $totalCount) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Get all attempts for a lesson (current user only).
     */
    public function getAttemptsForLesson($lessonId, $userId)
    {
        $lesson = Lesson::find($lessonId);
        if (!$lesson) {
            MessageService::abort(404, 'messages.lesson.not_found');
        }

        $attempts = UserLessonAttempt::where('lesson_id', $lessonId)
            ->where('user_id', $userId)
            ->orderBy('started_at', 'desc')
            ->get();

        $attemptIds = $attempts->pluck('id')->all();
        $answeredByAttempt = [];
        if (!empty($attemptIds)) {
            $answeredByAttempt = UserLessonQuestionAnswer::whereIn('attempt_id', $attemptIds)
                ->select('attempt_id', DB::raw('COUNT(*) as answered_count'))
                ->groupBy('attempt_id')
                ->pluck('answered_count', 'attempt_id')
                ->toArray();
        }

        $totalQuestions = LessonQuestion::where('lesson_id', $lessonId)->count();

        return $attempts->map(function ($attempt) use ($answeredByAttempt, $totalQuestions) {
            $answered = (int) ($answeredByAttempt[$attempt->id] ?? 0);
            return [
                'id' => $attempt->id,
                'user_id' => $attempt->user_id,
                'lesson_id' => $attempt->lesson_id,
                'status' => $attempt->status,
                'score' => $attempt->score,
                'current_question_id' => $attempt->current_question_id,
                'started_at' => $attempt->started_at,
                'finished_at' => $attempt->finished_at,
                'total_time' => $attempt->total_time,
                'video_watched' => $attempt->video_watched,
                'video_watched_at' => $attempt->video_watched_at,
                'progress' => [
                    'answered' => $answered,
                    'total' => $totalQuestions,
                    'percentage' => $totalQuestions > 0 ? round(($answered / $totalQuestions) * 100, 2) : 0,
                ],
            ];
        })->values()->all();
    }

    /**
     * Get full review payload for one attempt.
     */
    public function getAttemptReview($lessonId, $attemptId, $userId)
    {
        $attempt = UserLessonAttempt::where('id', $attemptId)
            ->where('user_id', $userId)
            ->first();
        if (!$attempt) {
            MessageService::abort(404, 'messages.attempt.not_found');
        }
        if ((int) $attempt->lesson_id !== (int) $lessonId) {
            MessageService::abort(400, 'messages.question.not_belongs_to_lesson');
        }

        $questions = LessonQuestion::where('lesson_id', $lessonId)
            ->with(['lessonQuestionOptions' => function ($q) {
                $q->orderBy('order_index');
            }])
            ->orderBy('order_index')
            ->get();

        $answers = UserLessonQuestionAnswer::where('attempt_id', $attemptId)
            ->with([
                'userLessonAnswerOptions.lessonQuestionOption',
                'userLessonAnswerMatches.leftOption',
                'userLessonAnswerMatches.rightOption',
            ])
            ->get()
            ->keyBy('question_id');

        $questionsPayload = $questions->map(function ($question) use ($answers) {
            $answer = $answers->get($question->id);
            $options = $question->lessonQuestionOptions->map(function ($option) {
                return [
                    'id' => $option->id,
                    'option_text' => $option->option_text,
                    'pair_key' => $option->pair_key,
                    'is_correct' => $option->is_correct,
                    'attached_path' => $option->attached_path,
                    'order_index' => $option->order_index,
                ];
            })->values()->all();

            [$leftOptions, $rightOptions] = $this->buildMatchColumns($question->lessonQuestionOptions);

            $userAnswerPayload = null;
            if ($answer) {
                $userAnswerPayload = [
                    'is_correct' => $answer->is_correct,
                    'score' => $answer->score,
                    'answered_at' => $answer->answered_at,
                    'options' => $answer->userLessonAnswerOptions->map(function ($answerOption) {
                        return [
                            'option_id' => $answerOption->option_id,
                            'option_text' => optional($answerOption->lessonQuestionOption)->option_text,
                            'is_correct' => $answerOption->is_correct,
                        ];
                    })->values()->all(),
                    'matches' => $answer->userLessonAnswerMatches->map(function ($match) {
                        return [
                            'left_option_id' => $match->left_option_id,
                            'left_option_text' => optional($match->leftOption)->option_text,
                            'right_option_id' => $match->right_option_id,
                            'right_option_text' => optional($match->rightOption)->option_text,
                            'is_correct' => $match->is_correct,
                        ];
                    })->values()->all(),
                ];
            }

            $correctAnswerPayload = null;
            if ($question->type === 'single_choice' || $question->type === 'true_false') {
                $correctOption = $question->lessonQuestionOptions->firstWhere('is_correct', true);
                $correctAnswerPayload = [
                    'correct_option_id' => $correctOption?->id,
                    'correct_option_text' => $correctOption?->option_text,
                ];
            } elseif ($question->type === 'multiple_choice') {
                $correctOptions = $question->lessonQuestionOptions->where('is_correct', true)->values();
                $correctAnswerPayload = [
                    'correct_option_ids' => $correctOptions->pluck('id')->values()->all(),
                    'correct_option_texts' => $correctOptions->pluck('option_text')->values()->all(),
                ];
            } elseif ($question->type === 'match') {
                $correctPairs = $this->buildCorrectMatchPairs($question->lessonQuestionOptions);
                $correctAnswerPayload = [
                    'correct_pairs' => $correctPairs,
                    'correct_count' => $answer ? $answer->userLessonAnswerMatches->where('is_correct', true)->count() : 0,
                    'total_count' => $answer ? $answer->userLessonAnswerMatches->count() : count($correctPairs),
                ];
            }

            return [
                'id' => $question->id,
                'type' => $question->type,
                'question_text' => $question->question_text,
                'attached_path' => $question->attached_path,
                'explanation' => $question->explanation,
                'score' => $question->score,
                'order_index' => $question->order_index,
                'options' => $options,
                'left_options' => $leftOptions,
                'right_options' => $rightOptions,
                'user_answer' => $userAnswerPayload,
                'correct_answer_payload' => $correctAnswerPayload,
            ];
        })->values()->all();

        $answeredCount = $answers->count();
        $totalQuestions = $questions->count();

        return [
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'score' => $attempt->score,
                'started_at' => $attempt->started_at,
                'finished_at' => $attempt->finished_at,
                'total_time' => $attempt->total_time,
                'video_watched' => $attempt->video_watched,
                'video_watched_at' => $attempt->video_watched_at,
                'progress' => [
                    'answered' => $answeredCount,
                    'total' => $totalQuestions,
                    'percentage' => $totalQuestions > 0 ? round(($answeredCount / $totalQuestions) * 100, 2) : 0,
                ],
            ],
            'questions' => $questionsPayload,
        ];
    }

    /**
     * Build left/right columns for match review.
     */
    private function buildMatchColumns($options)
    {
        if (!$options || $options->isEmpty()) {
            return [[], []];
        }

        $hasNull = $options->contains(fn ($o) => $o->pair_key === null || $o->pair_key === '');
        $hasNonNull = $options->contains(fn ($o) => $o->pair_key !== null && $o->pair_key !== '');

        $toOption = function ($option) {
            return [
                'id' => $option->id,
                'option_text' => $option->option_text,
                'pair_key' => $option->pair_key,
                'is_correct' => $option->is_correct,
                'attached_path' => $option->attached_path,
                'order_index' => $option->order_index,
            ];
        };

        if ($hasNull && $hasNonNull) {
            $left = $options->filter(fn ($o) => $o->pair_key === null || $o->pair_key === '')
                ->sortBy('order_index')
                ->map($toOption)
                ->values()
                ->all();
            $right = $options->filter(fn ($o) => $o->pair_key !== null && $o->pair_key !== '')
                ->sortBy('order_index')
                ->map($toOption)
                ->values()
                ->all();
            return [$left, $right];
        }

        $grouped = $options->groupBy('pair_key');
        $pairs = [];
        foreach ($grouped as $group) {
            $sorted = $group->sortBy('order_index')->values();
            $first = $sorted->get(0);
            $second = $sorted->get(1);
            if ($first && $second) {
                $pairs[] = ['left' => $first, 'right' => $second];
            }
        }

        $leftItems = array_map(fn ($pair) => $pair['left'], $pairs);
        $rightItems = array_map(fn ($pair) => $pair['right'], $pairs);
        usort($leftItems, fn ($a, $b) => $a->order_index <=> $b->order_index);
        usort($rightItems, fn ($a, $b) => $a->order_index <=> $b->order_index);

        return [
            array_map($toOption, $leftItems),
            array_map($toOption, $rightItems),
        ];
    }

    /**
     * Build correct match pairs for review payload.
     */
    private function buildCorrectMatchPairs($options)
    {
        if (!$options || $options->isEmpty()) {
            return [];
        }

        $hasNull = $options->contains(fn ($o) => $o->pair_key === null || $o->pair_key === '');
        $hasNonNull = $options->contains(fn ($o) => $o->pair_key !== null && $o->pair_key !== '');

        $pairs = [];

        if ($hasNull && $hasNonNull) {
            $rightWithKeys = $options->filter(fn ($o) => $o->pair_key !== null && $o->pair_key !== '')
                ->sortBy('order_index')
                ->values();
            $leftWithoutKeys = $options->filter(fn ($o) => $o->pair_key === null || $o->pair_key === '')
                ->sortBy('order_index')
                ->values();

            $count = min($leftWithoutKeys->count(), $rightWithKeys->count());
            for ($i = 0; $i < $count; $i++) {
                $left = $leftWithoutKeys->get($i);
                $right = $rightWithKeys->get($i);
                $pairs[] = [
                    'left_option_id' => $left->id,
                    'left_option_text' => $left->option_text,
                    'right_option_id' => $right->id,
                    'right_option_text' => $right->option_text,
                    'pair_key' => $right->pair_key,
                ];
            }

            return $pairs;
        }

        $grouped = $options->groupBy('pair_key');
        foreach ($grouped as $key => $group) {
            $sorted = $group->sortBy('order_index')->values();
            $left = $sorted->get(0);
            $right = $sorted->get(1);
            if ($left && $right) {
                $pairs[] = [
                    'left_option_id' => $left->id,
                    'left_option_text' => $left->option_text,
                    'right_option_id' => $right->id,
                    'right_option_text' => $right->option_text,
                    'pair_key' => $key,
                ];
            }
        }

        return $pairs;
    }

    /**
     * جلب السؤال الحالي بالتفاصيل الكاملة
     */
    public function getCurrentQuestion($attemptId, $userId)
    {
        $attempt = UserLessonAttempt::with(['currentQuestion.lessonQuestionOptions', 'lesson'])
            ->find($attemptId);

        if (!$attempt) {
            MessageService::abort(404, 'messages.attempt.not_found');
        }

        if ((int) $attempt->user_id !== (int) $userId) {
            MessageService::abort(403, 'messages.attempt.unauthorized');
        }

        // إذا انتهت المحاولة، إرجاع ملخص النتائج بدلاً من خطأ
        if ($attempt->status === 'finished') {
            $answers = UserLessonQuestionAnswer::where('attempt_id', $attemptId)
                ->with(['userLessonAnswerOptions.lessonQuestionOption', 'userLessonAnswerMatches.leftOption', 'userLessonAnswerMatches.rightOption'])
                ->get();
            
            $correctAnswers = $answers->where('is_correct', true)->count();
            $totalQuestions = LessonQuestion::where('lesson_id', $attempt->lesson_id)->count();

            return [
                'attempt_finished' => true,
                'summary' => [
                    'final_score' => $attempt->score,
                    'total_questions' => $totalQuestions,
                    'correct_answers' => $correctAnswers,
                    'wrong_answers' => $totalQuestions - $correctAnswers,
                    'finished_at' => $attempt->finished_at,
                    'started_at' => $attempt->started_at,
                    'total_time' => $attempt->finished_at && $attempt->started_at 
                        ? $attempt->finished_at->diffInSeconds($attempt->started_at) 
                        : null,
                ],
            ];
        }

        $question = $attempt->currentQuestion;
        if (!$question) {
            MessageService::abort(404, 'messages.question.not_found');
        }

        // جلب الإجابة إذا كانت موجودة
        $userAnswer = UserLessonQuestionAnswer::where('attempt_id', $attemptId)
            ->where('question_id', $question->id)
            ->with(['userLessonAnswerOptions.lessonQuestionOption', 'userLessonAnswerMatches.leftOption', 'userLessonAnswerMatches.rightOption'])
            ->first();

        $isAnswered = $userAnswer !== null;

        // تحضير الخيارات
        $options = $question->lessonQuestionOptions->map(function ($option) use ($isAnswered, $userAnswer) {
            $optionData = [
                'id' => $option->id,
                'option_text' => $option->option_text,
                'pair_key' => $option->pair_key,
                'attached_path' => $option->attached_path,
                'order_index' => $option->order_index,
            ];

            // إظهار is_correct فقط إذا تم الإجابة
            if ($isAnswered) {
                $optionData['is_correct'] = $option->is_correct;
            }

            return $optionData;
        });

        $questionPayload = [
            'id' => $question->id,
            'type' => $question->type,
            'question_text' => $question->question_text,
            'attached_path' => $question->attached_path,
            'explanation' => $isAnswered ? $question->explanation : null,
            'score' => $question->score,
            'order_index' => $question->order_index,
            'options' => $options,
        ];

        // لأسئلة المطابقة: بناء left_options و right_options من pair_key فقط
        if ($question->type === 'match') {
            $opts = $question->lessonQuestionOptions;
            $hasNull = $opts->contains(fn ($o) => $o->pair_key === null || $o->pair_key === '');
            $hasNonNull = $opts->contains(fn ($o) => $o->pair_key !== null && $o->pair_key !== '');

            if ($hasNull && $hasNonNull) {
                // نموذج قديم: اليسار بدون pair_key، اليمين له pair_key
                $leftModels = $opts->filter(fn ($o) => $o->pair_key === null || $o->pair_key === '')->sortBy('order_index')->values();
                $rightModels = $opts->filter(fn ($o) => $o->pair_key !== null && $o->pair_key !== '')->sortBy('order_index')->values();
                $questionPayload['left_options'] = $leftModels->map(function ($option) use ($isAnswered) {
                    $arr = [
                        'id' => $option->id,
                        'option_text' => $option->option_text,
                        'pair_key' => $option->pair_key,
                        'attached_path' => $option->attached_path,
                        'order_index' => $option->order_index,
                    ];
                    if ($isAnswered) {
                        $arr['is_correct'] = $option->is_correct;
                    }
                    return $arr;
                })->values()->all();
                $questionPayload['right_options'] = $rightModels->map(function ($option) use ($isAnswered) {
                    $arr = [
                        'id' => $option->id,
                        'option_text' => $option->option_text,
                        'pair_key' => $option->pair_key,
                        'attached_path' => $option->attached_path,
                        'order_index' => $option->order_index,
                    ];
                    if ($isAnswered) {
                        $arr['is_correct'] = $option->is_correct;
                    }
                    return $arr;
                })->values()->all();
            } elseif (!$hasNull) {
                // نموذج الأزواج: كل الخيارات لها pair_key، كل مفتاح مرتين → أصغر order_index = عمود (أ)، الآخر = (ب)
                $grouped = $opts->groupBy('pair_key');
                $pairs = [];
                foreach ($grouped as $key => $group) {
                    $sorted = $group->sortBy('order_index')->values();
                    $first = $sorted->get(0);
                    $second = $sorted->get(1);
                    if ($first && $second) {
                        $pairs[] = ['left' => $first, 'right' => $second];
                    }
                }
                $toOptionArr = function ($o, $isAnswered) {
                    $arr = [
                        'id' => $o->id,
                        'option_text' => $o->option_text,
                        'pair_key' => $o->pair_key,
                        'attached_path' => $o->attached_path,
                        'order_index' => $o->order_index,
                    ];
                    if ($isAnswered) {
                        $arr['is_correct'] = $o->is_correct;
                    }
                    return $arr;
                };
                $leftItems = array_map(fn ($p) => $p['left'], $pairs);
                $rightItems = array_map(fn ($p) => $p['right'], $pairs);
                usort($leftItems, fn ($a, $b) => $a->order_index <=> $b->order_index);
                usort($rightItems, fn ($a, $b) => $a->order_index <=> $b->order_index);
                $questionPayload['left_options'] = array_map(fn ($o) => $toOptionArr($o, $isAnswered), $leftItems);
                $questionPayload['right_options'] = array_map(fn ($o) => $toOptionArr($o, $isAnswered), $rightItems);
            }
        }

        return [
            'question' => $questionPayload,
            'user_answer' => $isAnswered ? [
                'is_correct' => $userAnswer->is_correct,
                'score' => $userAnswer->score,
                'answered_at' => $userAnswer->answered_at,
                'options' => $userAnswer->userLessonAnswerOptions->map(function ($answerOption) {
                    return [
                        'option_id' => $answerOption->option_id,
                        'is_correct' => $answerOption->is_correct,
                    ];
                }),
                'matches' => $userAnswer->userLessonAnswerMatches->map(function ($match) {
                    return [
                        'left_option_id' => $match->left_option_id,
                        'right_option_id' => $match->right_option_id,
                        'is_correct' => $match->is_correct,
                    ];
                }),
            ] : null,
        ];
    }

    /**
     * إرسال إجابة على سؤال
     */
    public function submitAnswer($attemptId, $questionId, $answerData, $userId)
    {
        $attempt = UserLessonAttempt::find($attemptId);
        if (!$attempt) {
            MessageService::abort(404, 'messages.attempt.not_found');
        }

        if ($attempt->user_id != $userId) {
            MessageService::abort(403, 'messages.attempt.unauthorized');
        }

        if ($attempt->status === 'finished') {
            MessageService::abort(400, 'messages.attempt.already_finished');
        }

        $question = LessonQuestion::with('lessonQuestionOptions')->find($questionId);
        if (!$question) {
            MessageService::abort(404, 'messages.question.not_found');
        }

        if ($question->lesson_id != $attempt->lesson_id) {
            MessageService::abort(400, 'messages.question.not_belongs_to_lesson');
        }

        // التحقق من أن السؤال لم يتم الإجابة عليه مسبقاً
        $existingAnswer = UserLessonQuestionAnswer::where('attempt_id', $attemptId)
            ->where('question_id', $questionId)
            ->first();

        if ($existingAnswer) {
            MessageService::abort(400, 'messages.question.already_answered');
        }

        // التحقق من أن هذا هو السؤال الحالي
        if ($attempt->current_question_id != $questionId) {
            MessageService::abort(400, 'messages.question.not_current');
        }

        // معالجة الإجابة حسب نوع السؤال
        $result = $this->processAnswer($question, $answerData);

        // حساب step_index
        $stepIndex = UserLessonQuestionAnswer::where('attempt_id', $attemptId)->count() + 1;

        // حفظ الإجابة
        $userAnswer = UserLessonQuestionAnswer::create([
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'step_index' => $stepIndex,
            'is_correct' => $result['is_correct'],
            'score' => $result['score'],
            'answered_at' => now(),
        ]);

        // حفظ تفاصيل الإجابة
        $this->saveAnswerDetails($userAnswer, $question, $answerData, $result);

        // تحديث current_question_id إلى السؤال التالي
        $nextQuestion = LessonQuestion::where('lesson_id', $question->lesson_id)
            ->where('order_index', '>', $question->order_index)
            ->orderBy('order_index')
            ->first();

        $attempt->current_question_id = $nextQuestion ? $nextQuestion->id : null;
        
        // إذا كان آخر سؤال، إنهاء المحاولة
        if (!$nextQuestion) {
            $attempt->status = 'finished';
            $attempt->finished_at = now();
        }

        $attempt->save();

        // حساب النتيجة الإجمالية
        $this->calculateScore($attemptId);

        // Update progress after answering
        $trackProgressService = app(TrackProgressService::class);
        $trackProgressService->calculateAndUpdateLessonProgress($attempt->lesson, $attempt->user_id, $attempt);

        $data = [
            'is_correct' => $result['is_correct'],
            'score' => $result['score'],
            'explanation' => $question->explanation,
            'next_question_id' => $nextQuestion ? $nextQuestion->id : null,
            'attempt_finished' => !$nextQuestion,
        ];
        if (isset($result['correct_count']) && isset($result['total_count'])) {
            $data['correct_count'] = $result['correct_count'];
            $data['total_count'] = $result['total_count'];
        }
        return $data;
    }

    /**
     * معالجة الإجابة حسب نوع السؤال
     */
    private function processAnswer($question, $answerData)
    {
        $options = $question->lessonQuestionOptions;
        $isCorrect = false;
        $score = 0;
        $correctCount = null;
        $totalCount = null;

        switch ($question->type) {
            case 'single_choice':
            case 'true_false':
                if (!isset($answerData['option_id'])) {
                    MessageService::abort(400, 'messages.answer.option_id_required');
                }

                $selectedOption = $options->find($answerData['option_id']);
                if (!$selectedOption) {
                    MessageService::abort(400, 'messages.answer.invalid_option');
                }

                $isCorrect = $selectedOption->is_correct;
                $score = $isCorrect ? ($question->score ?? 1) : 0;
                break;

            case 'multiple_choice':
                if (!isset($answerData['option_ids']) || !is_array($answerData['option_ids'])) {
                    MessageService::abort(400, 'messages.answer.option_ids_required');
                }

                $selectedOptionIds = $answerData['option_ids'];
                $correctOptionIds = $options->where('is_correct', true)->pluck('id')->toArray();

                // التحقق من أن جميع الخيارات المختارة موجودة
                foreach ($selectedOptionIds as $optionId) {
                    if (!$options->contains('id', $optionId)) {
                        MessageService::abort(400, 'messages.answer.invalid_option');
                    }
                }

                // المقارنة: يجب أن تكون الخيارات المختارة مطابقة تماماً للخيارات الصحيحة
                sort($selectedOptionIds);
                sort($correctOptionIds);
                $isCorrect = $selectedOptionIds === $correctOptionIds;

                $score = $isCorrect ? ($question->score ?? 1) : 0;
                break;

            case 'match':
                if (!isset($answerData['matches']) || !is_array($answerData['matches'])) {
                    MessageService::abort(400, 'messages.answer.matches_required');
                }

                $matches = $answerData['matches'];
                $allCorrect = true;
                $correctCount = 0;
                $totalMatches = count($matches);

                // التحقق من أن جميع الخيارات موجودة
                $allOptionIds = $options->pluck('id')->toArray();
                foreach ($matches as $match) {
                    if (!isset($match['left_option_id']) || !isset($match['right_option_id'])) {
                        MessageService::abort(400, 'messages.answer.invalid_match_format');
                    }

                    if (!in_array($match['left_option_id'], $allOptionIds) ||
                        !in_array($match['right_option_id'], $allOptionIds)) {
                        MessageService::abort(400, 'messages.answer.invalid_option');
                    }
                }

                $hasNull = $options->contains(fn ($o) => $o->pair_key === null || $o->pair_key === '');
                $hasNonNull = $options->contains(fn ($o) => $o->pair_key !== null && $o->pair_key !== '');

                if ($hasNull && $hasNonNull) {
                    // نموذج قديم: اليسار بدون pair_key، اليمين له pair_key، المطابقة حسب ترتيب الموضع
                    $rightOptionsWithKeys = $options->filter(fn ($o) => $o->pair_key !== null && $o->pair_key !== '')
                        ->sortBy('order_index')->values();
                    $leftOptionsWithoutKeys = $options->filter(fn ($o) => $o->pair_key === null || $o->pair_key === '')
                        ->sortBy('order_index')->values();

                    foreach ($matches as $match) {
                        $leftOption = $options->find($match['left_option_id']);
                        $rightOption = $options->find($match['right_option_id']);

                        if (!$leftOption || !$rightOption) {
                            $allCorrect = false;
                            continue;
                        }

                        $rightIndex = $rightOptionsWithKeys->search(fn ($item) => $item->id === $rightOption->id);
                        $leftIndex = $leftOptionsWithoutKeys->search(fn ($item) => $item->id === $leftOption->id);

                        if ($rightOption->pair_key !== null &&
                            ($leftOption->pair_key === null || $leftOption->pair_key === '') &&
                            $rightIndex !== false &&
                            $leftIndex !== false &&
                            $rightIndex === $leftIndex) {
                            $correctCount++;
                        } else {
                            $allCorrect = false;
                        }
                    }
                } else {
                    // نموذج الأزواج: كل الخيارات لها pair_key، الصحيح = تساوي المفتاح
                    foreach ($matches as $match) {
                        $leftOption = $options->find($match['left_option_id']);
                        $rightOption = $options->find($match['right_option_id']);

                        if (!$leftOption || !$rightOption) {
                            $allCorrect = false;
                            continue;
                        }

                        if ((string) $leftOption->pair_key === (string) $rightOption->pair_key) {
                            $correctCount++;
                        } else {
                            $allCorrect = false;
                        }
                    }
                }

                $isCorrect = $allCorrect && $correctCount === $totalMatches;
                $score = $totalMatches > 0 ? (($correctCount / $totalMatches) * ($question->score ?? 1)) : 0;
                $totalCount = $totalMatches;
                break;

            default:
                MessageService::abort(400, 'messages.question.invalid_type');
        }

        $result = [
            'is_correct' => $isCorrect,
            'score' => $score,
        ];
        if ($correctCount !== null && $totalCount !== null) {
            $result['correct_count'] = $correctCount;
            $result['total_count'] = $totalCount;
        }
        return $result;
    }

    /**
     * حفظ تفاصيل الإجابة
     */
    private function saveAnswerDetails($userAnswer, $question, $answerData, $result)
    {
        switch ($question->type) {
            case 'single_choice':
            case 'true_false':
                if (isset($answerData['option_id'])) {
                    $selectedOption = $question->lessonQuestionOptions->find($answerData['option_id']);
                    if ($selectedOption) {
                        UserLessonAnswerOption::create([
                            'user_answer_id' => $userAnswer->id,
                            'option_id' => $answerData['option_id'],
                            'is_correct' => $selectedOption->is_correct,
                        ]);
                    }
                }
                break;

            case 'multiple_choice':
                if (isset($answerData['option_ids']) && is_array($answerData['option_ids'])) {
                    foreach ($answerData['option_ids'] as $optionId) {
                        $selectedOption = $question->lessonQuestionOptions->find($optionId);
                        if ($selectedOption) {
                            UserLessonAnswerOption::create([
                                'user_answer_id' => $userAnswer->id,
                                'option_id' => $optionId,
                                'is_correct' => $selectedOption->is_correct,
                            ]);
                        }
                    }
                }
                break;

            case 'match':
                if (isset($answerData['matches']) && is_array($answerData['matches'])) {
                    $opts = $question->lessonQuestionOptions;
                    $hasNull = $opts->contains(fn ($o) => $o->pair_key === null || $o->pair_key === '');
                    $hasNonNull = $opts->contains(fn ($o) => $o->pair_key !== null && $o->pair_key !== '');

                    foreach ($answerData['matches'] as $match) {
                        $leftOption = $opts->find($match['left_option_id']);
                        $rightOption = $opts->find($match['right_option_id']);

                        $isMatchCorrect = false;
                        if ($leftOption && $rightOption) {
                            if ($hasNull && $hasNonNull) {
                                $rightOptionsWithKeys = $opts->filter(fn ($o) => $o->pair_key !== null && $o->pair_key !== '')
                                    ->sortBy('order_index')->values();
                                $leftOptionsWithoutKeys = $opts->filter(fn ($o) => $o->pair_key === null || $o->pair_key === '')
                                    ->sortBy('order_index')->values();
                                $rightIndex = $rightOptionsWithKeys->search(fn ($item) => $item->id === $rightOption->id);
                                $leftIndex = $leftOptionsWithoutKeys->search(fn ($item) => $item->id === $leftOption->id);
                                $isMatchCorrect = $rightOption->pair_key !== null
                                    && ($leftOption->pair_key === null || $leftOption->pair_key === '')
                                    && $rightIndex !== false
                                    && $leftIndex !== false
                                    && $rightIndex === $leftIndex;
                            } else {
                                $isMatchCorrect = (string) $leftOption->pair_key === (string) $rightOption->pair_key;
                            }
                        }

                        UserLessonAnswerMatch::create([
                            'user_answer_id' => $userAnswer->id,
                            'left_option_id' => $match['left_option_id'],
                            'right_option_id' => $match['right_option_id'],
                            'is_correct' => $isMatchCorrect,
                        ]);
                    }
                }
                break;
        }
    }

    /**
     * حساب النتيجة الإجمالية للمحاولة
     */
    public function calculateScore($attemptId)
    {
        $attempt = UserLessonAttempt::find($attemptId);
        if (!$attempt) {
            return;
        }

        $answers = UserLessonQuestionAnswer::where('attempt_id', $attemptId)->get();
        
        $totalScore = $answers->sum('score');
        $maxScore = LessonQuestion::where('lesson_id', $attempt->lesson_id)
            ->sum('score') ?? 1;

        $percentage = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;

        $attempt->score = round($percentage, 2);
        $attempt->save();
    }

    /**
     * إنهاء المحاولة
     */
    public function finishAttempt($attemptId, $userId)
    {
        $attempt = UserLessonAttempt::find($attemptId);
        if (!$attempt) {
            MessageService::abort(404, 'messages.attempt.not_found');
        }

        if ($attempt->user_id != $userId) {
            MessageService::abort(403, 'messages.attempt.unauthorized');
        }

        if ($attempt->status === 'finished') {
            MessageService::abort(400, 'messages.attempt.already_finished');
        }

        // حساب النتيجة النهائية
        $this->calculateScore($attemptId);
        $attempt->refresh();

        // إنهاء المحاولة
        $attempt->status = 'finished';
        $attempt->finished_at = now();
        $attempt->current_question_id = null;
        $attempt->save();

        // Update progress after finishing (100%)
        $trackProgressService = app(TrackProgressService::class);
        $trackProgressService->calculateAndUpdateLessonProgress($attempt->lesson, $attempt->user_id, $attempt);

        // جلب الإحصائيات
        $answers = UserLessonQuestionAnswer::where('attempt_id', $attemptId)->get();
        $correctAnswers = $answers->where('is_correct', true)->count();
        $totalQuestions = LessonQuestion::where('lesson_id', $attempt->lesson_id)->count();

        return [
            'final_score' => $attempt->score,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'finished_at' => $attempt->finished_at,
        ];
    }

    /**
     * Mark video as watched for a lesson attempt
     *
     * @param int $attemptId
     * @param int $userId
     * @return UserLessonAttempt
     */
    public function markVideoWatched($attemptId, $userId)
    {
        $attempt = UserLessonAttempt::find($attemptId);
        if (!$attempt) {
            MessageService::abort(404, 'messages.attempt.not_found');
        }

        if ($attempt->user_id != $userId) {
            MessageService::abort(403, 'messages.attempt.unauthorized');
        }

        if ($attempt->video_watched) {
            return $attempt; // Already watched
        }

        $attempt->video_watched = true;
        $attempt->video_watched_at = now();
        $attempt->save();

        // Update progress after marking video as watched
        $trackProgressService = app(TrackProgressService::class);
        $trackProgressService->calculateAndUpdateLessonProgress($attempt->lesson, $attempt->user_id, $attempt);

        return $attempt;
    }
}

