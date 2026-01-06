<?php

namespace App\Http\Services\Learning;

use App\Models\Learning\Lesson;
use App\Models\Learning\LessonQuestion;
use App\Models\Learning\LessonQuestionOption;
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
            'current_question_id' => $firstQuestion->id,
            'started_at' => now(),
        ]);

        return $attempt;
    }

    /**
     * جلب جميع أسئلة الدرس مع حالاتها
     */
    public function getQuestionsWithStatus($lessonId, $attemptId = null)
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
     * جلب السؤال الحالي بالتفاصيل الكاملة
     */
    public function getCurrentQuestion($attemptId)
    {
        $attempt = UserLessonAttempt::with(['currentQuestion.lessonQuestionOptions'])
            ->find($attemptId);

        if (!$attempt) {
            MessageService::abort(404, 'messages.attempt.not_found');
        }

        if ($attempt->status === 'finished') {
            MessageService::abort(400, 'messages.attempt.already_finished');
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

        return [
            'question' => [
                'id' => $question->id,
                'type' => $question->type,
                'question_text' => $question->question_text,
                'attached_path' => $question->attached_path,
                'explanation' => $isAnswered ? $question->explanation : null,
                'score' => $question->score,
                'order_index' => $question->order_index,
                'options' => $options,
            ],
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

        return [
            'is_correct' => $result['is_correct'],
            'score' => $result['score'],
            'explanation' => $question->explanation,
            'next_question_id' => $nextQuestion ? $nextQuestion->id : null,
            'attempt_finished' => !$nextQuestion,
        ];
    }

    /**
     * معالجة الإجابة حسب نوع السؤال
     */
    private function processAnswer($question, $answerData)
    {
        $options = $question->lessonQuestionOptions;
        $isCorrect = false;
        $score = 0;

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

                // التحقق من صحة كل match
                // في match، الخيارات اليسارية لها pair_key (مثل L1, L2)
                // والخيارات اليمينية يجب أن يكون لها نفس pair_key
                foreach ($matches as $match) {
                    $leftOption = $options->find($match['left_option_id']);
                    $rightOption = $options->find($match['right_option_id']);

                    // التحقق من أن pair_key للخيار الأيسر يطابق pair_key للخيار الأيمن
                    if ($leftOption && $rightOption && 
                        $leftOption->pair_key && $rightOption->pair_key &&
                        $leftOption->pair_key === $rightOption->pair_key) {
                        $correctCount++;
                    } else {
                        $allCorrect = false;
                    }
                }

                $isCorrect = $allCorrect && $correctCount === $totalMatches;
                // حساب النتيجة بناءً على عدد المطابقات الصحيحة
                $score = $totalMatches > 0 ? (($correctCount / $totalMatches) * ($question->score ?? 1)) : 0;
                break;

            default:
                MessageService::abort(400, 'messages.question.invalid_type');
        }

        return [
            'is_correct' => $isCorrect,
            'score' => $score,
        ];
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
                    foreach ($answerData['matches'] as $match) {
                        $leftOption = $question->lessonQuestionOptions->find($match['left_option_id']);
                        $rightOption = $question->lessonQuestionOptions->find($match['right_option_id']);

                        $isMatchCorrect = false;
                        // التحقق من أن pair_key للخيار الأيسر يطابق pair_key للخيار الأيمن
                        if ($leftOption && $rightOption && 
                            $leftOption->pair_key && $rightOption->pair_key &&
                            $leftOption->pair_key === $rightOption->pair_key) {
                            $isMatchCorrect = true;
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

        return $attempt;
    }
}

