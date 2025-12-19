<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class UpdateCourseRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'image_url' => 'sometimes|nullable|string|max:500',
            'is_published' => 'sometimes|nullable|boolean',
            'is_free' => 'sometimes|nullable|boolean',
        ];
    }
}
