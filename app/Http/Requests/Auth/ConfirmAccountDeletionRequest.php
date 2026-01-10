<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class ConfirmAccountDeletionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'code' => 'required|string|digits:5',
        ];
    }
}
