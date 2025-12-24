<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\BaseFormRequest;

class CreateArticleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:articles,slug',
            'content' => 'required|string',
            'author_id' => 'required|exists:users,id',
            'is_published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'image_url' => 'required|string|max:255',
        ];
    }
}
