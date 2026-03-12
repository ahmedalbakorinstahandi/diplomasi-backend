<?php

namespace App\Http\Services\Scenarios;

use App\Http\Permissions\Scenarios\ScenarioQuestionPermission;
use App\Models\Scenarios\Scenario;
use App\Models\Scenarios\ScenarioQuestion;
use App\Models\Scenarios\ScenarioQuestionOption;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;
use Illuminate\Support\Facades\DB;

class ScenarioQuestionAdminService
{
    public function index($filters = [])
    {
        $query = ScenarioQuestion::query()->with([
            'scenario',
            'scenarioQuestionOptions.nextQuestion',
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

        // // التحقق من عدم تكرار code في نفس السيناريو
        // $existingQuestion = ScenarioQuestion::where('scenario_id', $data['scenario_id'])
        //     ->where('code', $data['code'])
        //     ->first();
        // if ($existingQuestion) {
        //     MessageService::abort(422, 'messages.scenario_question.code_exists');
        // }

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

        // تعيين سؤال البداية تلقائياً إذا لم يكن محدداً.
        if (!$scenario->start_question_id) {
            $scenario->start_question_id = $question->id;
            $scenario->save();
        }

        // إنشاء الخيارات إذا كانت موجودة
        if (isset($data['options']) && is_array($data['options'])) {
            $this->createOptions($question, $data['options']);
        }

        $this->ensureScenarioStartQuestionIntegrity($scenario->id);

        // التحقق من سلامة التدفق بعد الإنشاء
        $validationResult = $this->validateScenarioFlow($scenario->id);
        if (!$validationResult['success']) {
            // في حالة فشل التحقق، نحذف السؤال الذي تم إنشاؤه
            $question->scenarioQuestionOptions()->delete();
            $question->delete();
            $this->ensureScenarioStartQuestionIntegrity($scenario->id);
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

        // // التحقق من عدم تكرار code إذا تم تحديثه
        // if (isset($data['code']) && $data['code'] != $question->code) {
        //     $existingQuestion = ScenarioQuestion::where('scenario_id', $question->scenario_id)
        //         ->where('code', $data['code'])
        //         ->where('id', '!=', $question->id)
        //         ->first();
        //     if ($existingQuestion) {
        //         MessageService::abort(422, 'messages.scenario_question.code_exists');
        //     }
        // }

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

        $this->ensureScenarioStartQuestionIntegrity($question->scenario_id);

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
        $scenario = Scenario::find($scenarioId);
        $wasStartQuestion = $scenario && $scenario->start_question_id == $question->id;

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

        // إذا حُذف سؤال البداية، نحدد بداية جديدة تلقائياً.
        if ($wasStartQuestion && $scenario) {
            $fallbackStartQuestion = ScenarioQuestion::where('scenario_id', $scenarioId)
                ->orderBy('order_index')
                ->orderBy('id')
                ->first();

            $scenario->start_question_id = $fallbackStartQuestion?->id;
            $scenario->save();
        }

        $this->ensureScenarioStartQuestionIntegrity($scenarioId);
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
                'feedback_text' => $optionData['feedback_text'] ?? null,
                'next_question_id' => $this->resolveNextQuestionId($optionData, $question->scenario_id),
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
                        'feedback_text' => $optionData['feedback_text'] ?? null,
                        'next_question_id' => $this->resolveNextQuestionId($optionData, $question->scenario_id),
                        'attached_path' => $optionData['attached_path'] ?? null,
                    ]);
                }
            } else {
                // إنشاء خيار جديد
                $option = ScenarioQuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => $optionData['option_text'],
                    'feedback_text' => $optionData['feedback_text'] ?? null,
                    'next_question_id' => $this->resolveNextQuestionId($optionData, $question->scenario_id),
                    'attached_path' => $optionData['attached_path'] ?? null,
                ]);

                OrderHelper::assign($option, 'order_index');
            }
        }
    }

    /**
     * تحويل next_question_code إلى next_question_id لسهولة الربط من الداش بورد.
     */
    private function resolveNextQuestionId(array $optionData, int $scenarioId): ?int
    {
        if (array_key_exists('next_question_id', $optionData)) {
            return $optionData['next_question_id'] ?: null;
        }

        if (!empty($optionData['next_question_code'])) {
            $nextQuestion = ScenarioQuestion::where('scenario_id', $scenarioId)
                ->where('code', $optionData['next_question_code'])
                ->first();

            if (!$nextQuestion) {
                MessageService::abort(422, trans('messages.scenario.invalid_next_question_code', [
                    'code' => $optionData['next_question_code'],
                ]));
            }

            return $nextQuestion->id;
        }

        return null;
    }

    /**
     * ضمان سلامة start_question_id دائماً مع أسئلة السيناريو الحالية.
     */
    private function ensureScenarioStartQuestionIntegrity(int $scenarioId): void
    {
        $scenario = Scenario::find($scenarioId);
        if (!$scenario) {
            return;
        }

        if ($scenario->start_question_id) {
            $isValid = ScenarioQuestion::where('scenario_id', $scenarioId)
                ->where('id', $scenario->start_question_id)
                ->exists();

            if ($isValid) {
                return;
            }
        }

        $fallbackStartQuestion = ScenarioQuestion::where('scenario_id', $scenarioId)
            ->orderBy('order_index')
            ->orderBy('id')
            ->first();

        $scenario->start_question_id = $fallbackStartQuestion?->id;
        $scenario->save();
    }

    /**
     * التحقق من سلامة التدفق في السيناريو
     * 
     * ملاحظة: هذا التحقق يركز على منع المشاكل الفعلية فقط (deadlock، next_question_id غير صحيح)
     * ولا يمنع إضافة أسئلة غير مرتبطة مؤقتاً (يمكن ربطها لاحقاً)
     */
    public function validateScenarioFlow($scenarioId, bool $strict = false)
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
                $uniqueNextIds = array_values(array_unique($nextIds));
                // إذا كان كل الخيارات تشير لنفس السؤال فقط (deadlock)
                if (count($uniqueNextIds) === 1 && $uniqueNextIds[0] == $questionId) {
                    return [
                        'success' => false,
                        'message' => trans('messages.scenario.deadlock_question', ['question_id' => $questionId]),
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
                        'message' => trans('messages.scenario.invalid_next_question', ['option_id' => $option->id]),
                    ];
                }
            }
        }

        if (!$strict) {
            return [
                'success' => true,
                'message' => null,
                'details' => [
                    'unreachable_question_ids' => [],
                    'has_terminal_path' => null,
                ],
            ];
        }

        if (!$scenario->start_question_id) {
            return [
                'success' => false,
                'message' => 'messages.scenario.no_start_question',
            ];
        }

        if (!in_array($scenario->start_question_id, $allQuestionIds)) {
            return [
                'success' => false,
                'message' => 'messages.scenario.invalid_start_question',
            ];
        }

        // BFS من سؤال البداية لاكتشاف القابلية للوصول ووجود مسار نهائي.
        $visited = [];
        $queue = [$scenario->start_question_id];
        $hasTerminalPath = false;

        while (!empty($queue)) {
            $currentQuestionId = array_shift($queue);
            if (isset($visited[$currentQuestionId])) {
                continue;
            }
            $visited[$currentQuestionId] = true;

            $options = $optionsMap[$currentQuestionId] ?? collect();
            $nextIds = $options->pluck('next_question_id')->filter()->toArray();

            // مسار نهائي = يمكن الوصول لسؤال فيه خيار واحد على الأقل ينتهي بالتدفق (next_question_id = null)
            if ($options->isEmpty() || $options->whereNull('next_question_id')->isNotEmpty()) {
                $hasTerminalPath = true;
            }

            foreach ($nextIds as $nextId) {
                if (!isset($visited[$nextId])) {
                    $queue[] = $nextId;
                }
            }
        }

        $reachableQuestionIds = array_keys($visited);
        $unreachableQuestionIds = array_values(array_diff($allQuestionIds, $reachableQuestionIds));

        if (!$hasTerminalPath) {
            return [
                'success' => false,
                'message' => 'messages.scenario.no_terminal_path',
                'details' => [
                    'unreachable_question_ids' => $unreachableQuestionIds,
                    'has_terminal_path' => false,
                ],
            ];
        }

        if (!empty($unreachableQuestionIds)) {
            return [
                'success' => false,
                'message' => trans('messages.scenario.unreachable_questions', [
                    'question_ids' => implode(', ', $unreachableQuestionIds),
                ]),
                'details' => [
                    'unreachable_question_ids' => $unreachableQuestionIds,
                    'has_terminal_path' => true,
                ],
            ];
        }

        return [
            'success' => true,
            'message' => null,
            'details' => [
                'unreachable_question_ids' => [],
                'has_terminal_path' => true,
            ],
        ];
    }

    public function validateFlow(int $scenarioId, bool $strict = true): array
    {
        $this->ensureScenarioStartQuestionIntegrity($scenarioId);
        return $this->validateScenarioFlow($scenarioId, $strict);
    }

    /**
     * استيراد محتوى السيناريو (شاشات + خيارات) من JSON دفعة واحدة.
     * يُستخدم لإدخال البيانات بسرعة دون المرور بالداش بورد شاشةً شاشة.
     *
     * @param int $scenarioId معرف السيناريو
     * @param array $data ['replace' => bool, 'screens' => [...]]
     * @return array ['success' => bool, 'message' => string, 'created_questions' => int, 'scenario' => Scenario]
     */
    public function importContent(int $scenarioId, array $data): array
    {
        $scenario = Scenario::find($scenarioId);
        if (!$scenario) {
            MessageService::abort(404, 'messages.scenario.not_found');
        }

        $replace = $data['replace'] ?? false;
        $screens = $data['screens'] ?? [];

        if (empty($screens)) {
            MessageService::abort(422, 'messages.scenario.import_screens_required');
        }

        DB::beginTransaction();
        try {
            if ($replace) {
                $this->deleteAllScenarioQuestions($scenarioId);
                $scenario->update(['start_question_id' => null]);
            }

            // المرور الأول: إنشاء جميع الأسئلة (بدون خيارات)، الـ code تلقائي "الشاشة 1"، "الشاشة 2" ...
            $indexToId = []; // 1-based: 1 => أول سؤال، 2 => ثاني سؤال
            foreach ($screens as $index => $screen) {
                $sequentialNumber = $index + 1;
                $question = ScenarioQuestion::create([
                    'scenario_id' => $scenarioId,
                    'code' => 'الشاشة ' . $sequentialNumber,
                    'type' => 'single_choice',
                    'question_text' => $screen['question_text'],
                    'explanation' => $screen['explanation'] ?? null,
                ]);
                OrderHelper::assign($question, 'order_index');
                $indexToId[$sequentialNumber] = $question->id;
            }

            // تعيين سؤال البداية إذا لم يكن محدداً
            $firstQuestionId = $indexToId[1] ?? null;
            if ($firstQuestionId && !$scenario->fresh()->start_question_id) {
                $scenario->update(['start_question_id' => $firstQuestionId]);
            }

            // المرور الثاني: إضافة الخيارات مع ربط next_question_id (المستخدم يرسل رقم الشاشة 1، 2، 3 أو retry/end)
            foreach ($screens as $index => $screen) {
                $currentIndex = $index + 1; // 1-based
                $questionId = $indexToId[$currentIndex] ?? null;
                if (!$questionId) {
                    continue;
                }
                $options = $screen['options'] ?? [];
                foreach ($options as $opt) {
                    $nextId = $this->resolveNextFromImport($opt['next'] ?? null, $currentIndex, $indexToId);
                    $option = ScenarioQuestionOption::create([
                        'question_id' => $questionId,
                        'option_text' => $opt['option_text'],
                        'feedback_text' => $opt['feedback_text'] ?? null,
                        'next_question_id' => $nextId,
                    ]);
                    OrderHelper::assign($option, 'order_index');
                }
            }

            $this->ensureScenarioStartQuestionIntegrity($scenarioId);
            $validationResult = $this->validateScenarioFlow($scenarioId);
            if (!$validationResult['success']) {
                DB::rollBack();
                MessageService::abort(422, $validationResult['message']);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $createdCount = count($screens);
        $scenario->load(['scenarioQuestions.scenarioQuestionOptions']);

        return [
            'success' => true,
            'message' => trans('messages.scenario.content_imported', ['count' => $createdCount]),
            'created_questions' => $createdCount,
            'scenario' => $scenario,
        ];
    }

    /**
     * حذف جميع أسئلة السيناريو (للاستبدال عند الاستيراد).
     */
    private function deleteAllScenarioQuestions(int $scenarioId): void
    {
        /** @var ScenarioQuestion[] $questions */
        $questions = ScenarioQuestion::where('scenario_id', $scenarioId)->get();
        foreach ($questions as $q) {
            $q->scenarioQuestionOptions()->delete();
            $q->delete();
        }
    }

    /**
     * تحويل قيمة next من صيغة الاستيراد إلى next_question_id.
     * - "1", "2", "3" ... → id الشاشة ذات الرقم التسلسلي (للعودة لشاشة معيّنة نضع رقمها)
     * - "end" أو null أو "" → null (نهاية السيناريو)
     *
     * @param int $currentIndex رقم الشاشة الحالية (1-based)
     * @param array<int,int> $indexToId خريطة رقم الشاشة => question id
     */
    private function resolveNextFromImport(?string $next, int $currentIndex, array $indexToId): ?int
    {
        if ($next === null || $next === '') {
            return null;
        }
        $next = trim($next);
        if (strtolower($next) === 'end') {
            return null;
        }
        $num = (int) $next;
        if ($num >= 1) {
            return $indexToId[$num] ?? null;
        }
        return null;
    }

}
