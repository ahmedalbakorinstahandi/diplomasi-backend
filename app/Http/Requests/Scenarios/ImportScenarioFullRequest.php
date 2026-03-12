<?php

namespace App\Http\Requests\Scenarios;

use App\Http\Requests\BaseFormRequest;

/**
 * طلب استيراد كامل: إنشاء سيناريو جديد + استيراد الشاشات والخيارات دفعة واحدة.
 * نفس هيكل الـ screens المعرّف في SCENARIO_IMPORT.md.
 */
class ImportScenarioFullRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'level_id' => 'required|exists:levels,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_free' => 'nullable|boolean',
            'replace' => 'nullable|boolean',
            'screens' => 'required|array|min:1',
            'screens.*.question_text' => 'required|string',
            'screens.*.explanation' => 'nullable|string',
            'screens.*.options' => 'required|array|min:1',
            'screens.*.options.*.option_text' => 'required|string',
            'screens.*.options.*.feedback_text' => 'nullable|string',
            'screens.*.options.*.next' => 'nullable|string|max:20',
        ];
    }
}
