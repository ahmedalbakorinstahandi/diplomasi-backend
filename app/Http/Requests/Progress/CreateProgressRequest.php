<?php

namespace App\Http\Requests\Progress;

use App\Http\Requests\BaseFormRequest;

class CreateProgressRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'course_id' => 'nullable|exists:courses,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'level_id' => 'nullable|exists:levels,id',
            'progress_percentage' => 'nullable|numeric|min:0|max:100',
            'completed_at' => 'nullable|date',
        ];
    }
}
