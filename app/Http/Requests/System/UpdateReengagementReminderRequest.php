<?php

namespace App\Http\Requests\System;

use App\Http\Requests\BaseFormRequest;
use App\Models\System\ReengagementReminder;

class UpdateReengagementReminderRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => 'sometimes|required|integer|min:1',
            'unit' => 'sometimes|required|string|in:' . implode(',', ReengagementReminder::UNITS),
            'title' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
            'is_active' => 'sometimes|nullable|boolean',
            'sort_order' => 'sometimes|nullable|integer|min:0',
        ];
    }
}
