<?php

namespace App\Http\Requests\System;

use App\Http\Requests\BaseFormRequest;
use App\Models\System\ReengagementReminder;

class CreateReengagementReminderRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => 'required|integer|min:1',
            'unit' => 'required|string|in:' . implode(',', ReengagementReminder::UNITS),
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
