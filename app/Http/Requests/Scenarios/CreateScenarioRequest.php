<?php

namespace App\Http\Requests\Scenarios;

use App\Http\Requests\BaseFormRequest;

class CreateScenarioRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'level_id' => 'required|exists:levels,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'is_free' => 'nullable|boolean',
        ];
    }
}
