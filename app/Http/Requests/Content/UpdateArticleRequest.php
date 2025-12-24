<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $articleId = $this->route('id');

        return [
            'title' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('articles', 'slug')->ignore($articleId)],
            'content' => 'sometimes|required|string',
            'author_id' => 'sometimes|required|exists:users,id',
            'is_published' => 'sometimes|nullable|boolean',
            'published_at' => 'sometimes|nullable|date',
            'image_url' => 'sometimes|nullable|string|max:255',
        ];
    }
}
