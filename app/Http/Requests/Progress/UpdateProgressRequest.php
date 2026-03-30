<?php

namespace App\Http\Requests\Progress;

use App\Http\Requests\BaseFormRequest;

class UpdateProgressRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'sometimes|exists:users,id',
            'course_id' => 'sometimes|nullable|exists:courses,id',
            'lesson_id' => 'sometimes|nullable|exists:lessons,id',
            'level_id' => 'sometimes|nullable|exists:levels,id',
            'progress_percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'completed_at' => 'sometimes|nullable|date',
        ];
    }
}
