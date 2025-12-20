<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $roleId = (int) ($this->route('id') ?? 0);

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                // Rule::unique('roles', 'name')->whereNull('deleted_at')->ignore($roleId),
            ],
            'description' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
