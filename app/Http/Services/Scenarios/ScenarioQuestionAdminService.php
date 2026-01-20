<?php

namespace App\Http\Services\Scenarios;

use App\Http\Permissions\Scenarios\ScenarioQuestionPermission;
use App\Models\Scenarios\Scenario;
use App\Models\Scenarios\ScenarioQuestion;
use App\Models\Scenarios\ScenarioQuestionOption;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;

class ScenarioQuestionAdminService
{
    public function index($filters = [])
    {
        $query = ScenarioQuestion::query()->with([
            'scenario',
            'scenarioQuestionOptions',
        ]);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'order_index';
        $filters['sort_order'] = $filters['sort_order'] ?? 'asc';

        $searchFields = ['question_text', 'explanation', 'code'];
        $numericFields = ['order_index'];
        $dateFields = ['created_at'];
        $exactMatchFields = ['scenario_id', 'type'];
        $inFields = [];

        $query = ScenarioQuestionPermission::filterIndex($query);

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
        $question = ScenarioQuestion::where('id', $id)->first();
        if (!$question) {
            MessageService::abort(404, 'messages.scenario_question.not_found');
        }

        $question->load([
            'scenario',
            'scenarioQuestionOptions.nextQuestion',
        ]);

        return $question;
    }

    public function create($data)
    {
        // التحقق من وجود السيناريو
        $scenario = Scenario::find($data['scenario_id']);
        if (!$scenario) {
            MessageService::abort(404, 'messages.scenario.not_found');
        }

        // التحقق من عدم تكرار code في نفس السيناريو
        $existingQuestion = ScenarioQuestion::where('scenario_id', $data['scenario_id'])
            ->where('code', $data['code'])
            ->first();
        if ($existingQuestion) {
            MessageService::abort(422, 'messages.scenario_question.code_exists');
        }

        // إنشاء السؤال
        $questionData = [
            'scenario_id' => $data['scenario_id'],
            'code' => $data['code'],
            'type' => $data['type'],
            'question_text' => $data['question_text'],
            'attached_path' => $data['attached_path'] ?? null,
            'explanation' => $data['explanation'] ?? null,
        ];

        $question = ScenarioQuestion::create($questionData);

        // تعيين order_index
        OrderHelper::assign($question, 'order_index');

        // إنشاء الخيارات إذا كانت موجودة
        if (isset($data['options']) && is_array($data['options'])) {
            $this->createOptions($question, $data['options']);
        }

        // التحقق من سلامة التدفق بعد الإنشاء
        $validationResult = $this->validateScenarioFlow($scenario->id);
        if (!$validationResult['success']) {
            // في حالة فشل التحقق، نحذف السؤال الذي تم إنشاؤه
            $question->scenarioQuestionOptions()->delete();
            $question->delete();
            MessageService::abort(422, $validationResult['message']);
        }

        $question = $this->show($question->id);

        return $question;
    }

    public function update($data, $question)
    {
        $oldScenarioId = $question->scenario_id;

        // التحقق من وجود السيناريو إذا تم تحديث scenario_id
        if (isset($data['scenario_id']) && $data['scenario_id'] != $question->scenario_id) {
            $scenario = Scenario::find($data['scenario_id']);
            if (!$scenario) {
                MessageService::abort(404, 'messages.scenario.not_found');
            }
        }

        // التحقق من عدم تكرار code إذا تم تحديثه
        if (isset($data['code']) && $data['code'] != $question->code) {
            $existingQuestion = ScenarioQuestion::where('scenario_id', $question->scenario_id)
                ->where('code', $data['code'])
                ->where('id', '!=', $question->id)
                ->first();
            if ($existingQuestion) {
                MessageService::abort(422, 'messages.scenario_question.code_exists');
            }
        }

        // تحديث بيانات السؤال
        $questionData = [];
        if (isset($data['scenario_id'])) {
            $questionData['scenario_id'] = $data['scenario_id'];
        }
        if (isset($data['code'])) {
            $questionData['code'] = $data['code'];
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

        if (!empty($questionData)) {
            $question->update($questionData);
        }

        // تحديث الخيارات إذا كانت موجودة
        if (isset($data['options']) && is_array($data['options'])) {
            $this->updateOptions($question, $data['options']);
        }

        // التحقق من سلامة التدفق بعد التحديث
        $scenarioId = $question->scenario_id;
        $validationResult = $this->validateScenarioFlow($scenarioId);
        if (!$validationResult['success']) {
            MessageService::abort(422, $validationResult['message']);
        }

        $question = $this->show($question->id);

        return $question;
    }

    public function delete($question)
    {
        $scenarioId = $question->scenario_id;

        // التحقق من أن السؤال ليس start_question_id
        $scenario = Scenario::find($scenarioId);
        if ($scenario && $scenario->start_question_id == $question->id) {
            MessageService::abort(422, 'messages.scenario_question.cannot_delete_start_question');
        }

        // التحقق من أن السؤال لا يُشار إليه من خيارات أخرى
        $referencedBy = ScenarioQuestionOption::where('next_question_id', $question->id)
            ->where('question_id', '!=', $question->id)
            ->count();
        
        if ($referencedBy > 0) {
            MessageService::abort(422, 'messages.scenario_question.cannot_delete_referenced');
        }

        // حذف الخيارات المرتبطة
        $question->scenarioQuestionOptions()->delete();

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
    private function createOptions(ScenarioQuestion $question, array $options)
    {
        foreach ($options as $index => $optionData) {
            $option = ScenarioQuestionOption::create([
                'question_id' => $question->id,
                'option_text' => $optionData['option_text'],
                'next_question_id' => $optionData['next_question_id'] ?? null,
                'attached_path' => $optionData['attached_path'] ?? null,
            ]);

            // تعيين order_index
            OrderHelper::assign($option, 'order_index');
        }
    }

    /**
     * تحديث خيارات السؤال
     */
    private function updateOptions(ScenarioQuestion $question, array $options)
    {
        $existingOptionIds = collect($options)->pluck('id')->filter()->toArray();
        
        // حذف الخيارات التي لم يتم إرسالها
        $question->scenarioQuestionOptions()
            ->whereNotIn('id', $existingOptionIds)
            ->delete();

        foreach ($options as $optionData) {
            if (isset($optionData['id'])) {
                // تحديث خيار موجود
                $option = ScenarioQuestionOption::find($optionData['id']);
                if ($option && $option->question_id == $question->id) {
                    $option->update([
                        'option_text' => $optionData['option_text'],
                        'next_question_id' => $optionData['next_question_id'] ?? null,
                        'attached_path' => $optionData['attached_path'] ?? null,
                    ]);
                }
            } else {
                // إنشاء خيار جديد
                $option = ScenarioQuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => $optionData['option_text'],
                    'next_question_id' => $optionData['next_question_id'] ?? null,
                    'attached_path' => $optionData['attached_path'] ?? null,
                ]);

                OrderHelper::assign($option, 'order_index');
            }
        }
    }

    /**
     * التحقق من سلامة التدفق في السيناريو
     * 
     * ملاحظة: هذا التحقق يركز على منع المشاكل الفعلية فقط (deadlock، next_question_id غير صحيح)
     * ولا يمنع إضافة أسئلة غير مرتبطة مؤقتاً (يمكن ربطها لاحقاً)
     */
    public function validateScenarioFlow($scenarioId)
    {
        // 1. جلب السيناريو
        $scenario = Scenario::find($scenarioId);
        if (!$scenario) {
            return [
                'success' => false,
                'message' => 'messages.scenario.not_found',
            ];
        }

        // 2. جلب جميع الأسئلة والخيارات
        $questions = ScenarioQuestion::where('scenario_id', $scenarioId)->get();
        if ($questions->isEmpty()) {
            // السماح بسيناريو بدون أسئلة (يمكن إضافتها لاحقاً)
            return [
                'success' => true,
                'message' => null,
            ];
        }

        // 3. بناء خريطة التدفق والخيارات
        $optionsMap = [];
        $nextQuestionsMap = [];
        $allQuestionIds = $questions->pluck('id')->toArray();

        foreach ($questions as $question) {
            $options = $question->scenarioQuestionOptions;
            $optionsMap[$question->id] = $options;

            $nextIds = $options->pluck('next_question_id')->filter()->toArray();
            $nextQuestionsMap[$question->id] = $nextIds;
        }

        // 4. التحقق من عدم وجود deadlock (حلقة مغلقة تماماً)
        // Deadlock: عندما يكون كل خيارات السؤال تشير لنفس السؤال فقط
        foreach ($allQuestionIds as $questionId) {
            $options = $optionsMap[$questionId];
            $nextIds = $nextQuestionsMap[$questionId];

            // إذا كان السؤال لديه خيارات وكلها تشير لنفس السؤال (loop نفسي بدون خروج)
            if (count($options) > 0 && count($nextIds) > 0) {
                $uniqueNextIds = array_unique($nextIds);
                // إذا كان كل الخيارات تشير لنفس السؤال فقط (deadlock)
                if (count($uniqueNextIds) === 1 && $uniqueNextIds[0] == $questionId) {
                    return [
                        'success' => false,
                        'message' => "messages.scenario.deadlock_question: {$questionId}",
                    ];
                }
            }
        }

        // 5. التحقق من أن next_question_id يشير لأسئلة في نفس السيناريو
        foreach ($optionsMap as $questionId => $options) {
            foreach ($options as $option) {
                if ($option->next_question_id && !in_array($option->next_question_id, $allQuestionIds)) {
                    return [
                        'success' => false,
                        'message' => "messages.scenario.invalid_next_question: {$option->id}",
                    ];
                }
            }
        }

        // ملاحظة: لا نتحقق من:
        // - وجود start_question_id (يمكن تحديده لاحقاً)
        // - وجود exit path (يمكن إضافته لاحقاً)
        // - الأسئلة غير القابلة للوصول (يمكن ربطها لاحقاً)

        return [
            'success' => true,
            'message' => null,
        ];
    }

}
