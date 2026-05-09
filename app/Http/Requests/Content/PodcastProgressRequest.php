<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class PodcastProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'position_seconds' => ['required', 'integer', 'min:0'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
