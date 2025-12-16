<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseFormRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;

    public function authorize(): bool
    {
        return true;
    }


    public function attributes()
    {
        $attributes = trans('validation.attributes');

        return is_array($attributes) ? $attributes : [];
    }



    public function messages()
    {
        $messages = trans('validation');

        return is_array($messages) ? $messages : [];
    }
}
