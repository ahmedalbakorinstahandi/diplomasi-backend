<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $pageId = $this->route('id');

        return [
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('pages', 'slug')->ignore($pageId)],
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'is_published' => 'sometimes|nullable|boolean',
        ];
    }
}
