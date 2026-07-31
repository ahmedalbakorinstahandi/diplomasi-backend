<?php

namespace App\Http\Requests\Negotiation;

use App\Http\Requests\BaseFormRequest;

class UpsertNegotiationSituationNoteRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'note_text' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
