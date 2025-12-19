<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SyncRolePermissionsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'permission_names' => ['required', 'array'],
            'permission_names.*' => [
                'required',
                'string',
                'max:255',
                Rule::exists('permissions', 'name')->whereNull('deleted_at'),
            ],
        ];
    }
}

