<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class UpdateLessonRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'level_id' => 'sometimes|required|exists:levels,id',
            'lesson_number' => 'sometimes|required|integer|min:1',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'video_url' => 'sometimes|nullable|string|max:500',
            'content' => 'sometimes|nullable|string',
            'is_published' => 'sometimes|nullable|boolean',
        ];
    }
}
