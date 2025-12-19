<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class UpdateLevelRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'course_id' => 'sometimes|required|exists:courses,id',
            'level_number' => 'sometimes|required|integer|min:1',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'is_published' => 'sometimes|nullable|boolean',
            'is_free' => 'sometimes|nullable|boolean',
            'has_certificate' => 'sometimes|nullable|boolean',
            'order_index' => 'sometimes|nullable|integer|min:0',
        ];
    }
}
