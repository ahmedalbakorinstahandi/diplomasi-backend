<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class CreateLessonRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'level_id' => 'required|exists:levels,id',
            'lesson_number' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'order_index' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
        ];
    }
}
