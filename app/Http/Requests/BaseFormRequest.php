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

        return $attributes;
    }



    public function messages()
    {
        return trans('validation');
    }
}
