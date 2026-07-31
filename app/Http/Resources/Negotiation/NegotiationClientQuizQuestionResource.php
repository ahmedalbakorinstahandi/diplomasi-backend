<?php

namespace App\Http\Resources\Negotiation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CLIENT-SAFE quiz question for START endpoints.
 * Structurally cannot expose correct_response_id or option styles.
 */
class NegotiationClientQuizQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'situation_id' => $this['situation_id'],
            'asked_style' => $this['asked_style'],
            'options' => array_map(static function (array $option): array {
                return [
                    'id' => $option['id'],
                    'response_text' => $option['response_text'],
                ];
            }, $this['options'] ?? []),
        ];
    }
}
