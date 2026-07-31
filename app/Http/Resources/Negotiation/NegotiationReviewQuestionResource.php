<?php

namespace App\Http\Resources\Negotiation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * REVIEW payload question: seed-reproduced options + past selection + correctness.
 * Distinct from NegotiationClientQuizQuestionResource so start/review cannot be mixed.
 */
class NegotiationReviewQuestionResource extends JsonResource
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
            'selected_response_id' => $this['selected_response_id'] ?? null,
            'is_correct' => $this['is_correct'] ?? null,
            'correct_response_id' => $this['correct_response_id'],
            'feedback' => $this['feedback'] ?? null,
        ];
    }
}
