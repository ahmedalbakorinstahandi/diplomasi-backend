<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class CreateLevelRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'course_id' => 'required|exists:courses,id',
            'level_number' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_published' => 'nullable|boolean',
            'is_free' => 'nullable|boolean',
            'has_certificate' => 'nullable|boolean',
        ];
    }
}
