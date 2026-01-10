<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class RequestAccountDeletionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // No additional fields required - user is authenticated
        ];
    }
}
