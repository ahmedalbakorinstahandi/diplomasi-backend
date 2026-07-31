<?php

namespace App\Http\Requests\Negotiation;

use Illuminate\Validation\Validator;

trait ValidatesNegotiationResponseStyles
{
    protected function validateDistinctResponseStyles(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->has('responses')) {
                return;
            }

            $responses = $this->input('responses');
            if (!is_array($responses) || count($responses) !== 3) {
                return;
            }

            $requiredStyles = ['gentle', 'diplomatic', 'firm'];

            $styles = collect($responses)
                ->pluck('style')
                ->filter(fn ($style) => is_string($style) && $style !== '')
                ->values();

            $unique = $styles->unique()->values();

            if (
                $styles->count() !== 3
                || $unique->count() !== 3
                || $unique->diff($requiredStyles)->isNotEmpty()
            ) {
                $validator->errors()->add(
                    'responses',
                    __('messages.negotiation_situation.responses_styles_invalid')
                );
            }
        });
    }
}
