<?php

namespace App\Http\Services\Learning;

use App\Http\Permissions\Learning\LessonQuestionPermission;
use App\Models\Learning\Lesson;
use App\Models\Learning\LessonQuestion;
use App\Models\Learning\LessonQuestionOption;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;

class LessonQuestionAdminService
{
    public function index($filters = [])
    {
        $query = LessonQuestion::query()->with([
            'lesson',
            'lessonQuestionOptions',
        ]);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'order_index';
        $filters['sort_order'] = $filters['sort_order'] ?? 'asc';

        $searchFields = ['question_text', 'explanation'];
        $numericFields = ['order_index', 'score'];
        $dateFields = ['created_at'];
        $exactMatchFields = ['lesson_id', 'type'];
        $inFields = [];

        $query = LessonQuestionPermission::filterIndex($query);

        $query = FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );

        return $query;
    }

    public function show(int $id)
    {
        $question = LessonQuestion::where('id', $id)->first();
        if (!$question) {
            MessageService::abort(404, 'messages.lesson_question.not_found');
        }

        $question->load([
            'lesson',
            'lessonQuestionOptions',
        ]);

        return $question;
    }

    public function create($data)
    {
        // التحقق من وجود الدرس
        $lesson = Lesson::find($data['lesson_id']);
        if (!$lesson) {
            MessageService::abort(404, 'messages.lesson.not_found');
        }

        // إنشاء السؤال
        $questionData = [
            'lesson_id' => $data['lesson_id'],
            'type' => $data['type'],
            'question_text' => $data['question_text'],
            'attached_path' => $data['attached_path'] ?? null,
            'explanation' => $data['explanation'] ?? null,
            'score' => $data['score'] ?? null,
        ];

        $question = LessonQuestion::create($questionData);

        // تعيين order_index
        OrderHelper::assign($question, 'order_index');

        // إنشاء الخيارات إذا كانت موجودة
        if (isset($data['options']) && is_array($data['options'])) {
            $this->createOptions($question, $data['options']);
        }

        $question = $this->show($question->id);

        return $question;
    }

    public function update($data, $question)
    {
        // التحقق من وجود الدرس إذا تم تحديث lesson_id
        if (isset($data['lesson_id']) && $data['lesson_id'] != $question->lesson_id) {
            $lesson = Lesson::find($data['lesson_id']);
            if (!$lesson) {
                MessageService::abort(404, 'messages.lesson.not_found');
            }
        }

        // تحديث بيانات السؤال
        $questionData = [];
        if (isset($data['lesson_id'])) {
            $questionData['lesson_id'] = $data['lesson_id'];
        }
        if (isset($data['type'])) {
            $questionData['type'] = $data['type'];
        }
        if (isset($data['question_text'])) {
            $questionData['question_text'] = $data['question_text'];
        }
        if (array_key_exists('attached_path', $data)) {
            $questionData['attached_path'] = $data['attached_path'];
        }
        if (array_key_exists('explanation', $data)) {
            $questionData['explanation'] = $data['explanation'];
        }
        if (array_key_exists('score', $data)) {
            $questionData['score'] = $data['score'];
        }

        if (!empty($questionData)) {
            $question->update($questionData);
        }

        // تحديث الخيارات إذا كانت موجودة
        if (isset($data['options']) && is_array($data['options'])) {
            $this->updateOptions($question, $data['options']);
        }

        $question = $this->show($question->id);

        return $question;
    }

    public function delete($question)
    {
        // حذف الخيارات المرتبطة
        $question->lessonQuestionOptions()->delete();

        $question->delete();
    }

    public function reorder($question, $validatedData)
    {
        OrderHelper::reorder($question, $validatedData['new_order_index'], 'order_index');

        return $question;
    }

    /**
     * إنشاء خيارات السؤال
     */
    private function createOptions(LessonQuestion $question, array $options)
    {
        foreach ($options as $index => $optionData) {
            $option = LessonQuestionOption::create([
                'question_id' => $question->id,
                'option_text' => $optionData['option_text'],
                'pair_key' => $optionData['pair_key'] ?? null,
                'is_correct' => $optionData['is_correct'] ?? false,
                'attached_path' => $optionData['attached_path'] ?? null,
            ]);

            // تعيين order_index
            OrderHelper::assign($option, 'order_index');
        }
    }

    /**
     * تحديث خيارات السؤال
     */
    private function updateOptions(LessonQuestion $question, array $options)
    {
        $existingOptionIds = collect($options)->pluck('id')->filter()->toArray();
        
        // حذف الخيارات التي لم يتم إرسالها
        $question->lessonQuestionOptions()
            ->whereNotIn('id', $existingOptionIds)
            ->delete();

        foreach ($options as $optionData) {
            if (isset($optionData['id'])) {
                // تحديث خيار موجود
                $option = LessonQuestionOption::find($optionData['id']);
                if ($option && $option->question_id == $question->id) {
                    $option->update([
                        'option_text' => $optionData['option_text'],
                        'pair_key' => $optionData['pair_key'] ?? null,
                        'is_correct' => $optionData['is_correct'] ?? false,
                        'attached_path' => $optionData['attached_path'] ?? null,
                    ]);
                }
            } else {
                // إنشاء خيار جديد
                $option = LessonQuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => $optionData['option_text'],
                    'pair_key' => $optionData['pair_key'] ?? null,
                    'is_correct' => $optionData['is_correct'] ?? false,
                    'attached_path' => $optionData['attached_path'] ?? null,
                ]);

                OrderHelper::assign($option, 'order_index');
            }
        }
    }
}
