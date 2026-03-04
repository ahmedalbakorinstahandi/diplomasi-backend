<?php

namespace App\Http\Requests\System;

use App\Http\Requests\BaseFormRequest;

class CreateNotificationRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'nullable|exists:users,id',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
            'send_to_all' => 'nullable|boolean',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'nullable|string|max:50',
            'data' => 'nullable|array',
        ];
    }

    public function withValidator($validator): void
    {
        if (!$this->isMethod('post')) {
            return;
        }

        $validator->after(function ($validator) {
            $targetCount = 0;

            if ($this->filled('user_id')) {
                $targetCount++;
            }
            if (!empty($this->input('user_ids', []))) {
                $targetCount++;
            }
            if ((bool) $this->input('send_to_all', false) === true) {
                $targetCount++;
            }

            if ($targetCount !== 1) {
                $validator->errors()->add('target', 'حدد هدفاً واحداً فقط: user_id أو user_ids أو send_to_all.');
            }
        });
    }
}
