<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class VerifyMoyasarPaymentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'merchant_reference_id' => 'required|uuid',
        ];
    }
}

