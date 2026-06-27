<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\BaseFormRequest;

class ContactFormRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ];
    }
}
