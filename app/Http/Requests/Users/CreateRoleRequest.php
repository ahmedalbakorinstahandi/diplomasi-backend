<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateRoleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // Rule::unique('roles', 'name')->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:1000',
            'is_default' => 'nullable|boolean',
        ];
    }
}

