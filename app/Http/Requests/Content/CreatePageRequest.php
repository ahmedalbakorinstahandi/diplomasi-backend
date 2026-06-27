<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\BaseFormRequest;

class CreatePageRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'slug' => 'required|string|max:255|unique:pages,slug',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_published' => 'nullable|boolean',
        ];
    }
}
