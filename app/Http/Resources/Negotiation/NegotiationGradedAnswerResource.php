<?php

namespace App\Http\Resources\Negotiation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Graded answer for SUBMIT responses (immediate feedback).
 * Includes correctness + correct_response_id + feedback.
 */
class NegotiationGradedAnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'situation_id' => $this['situation_id'] ?? null,
            'asked_style' => $this['asked_style'],
            'selected_response_id' => $this['selected_response_id'] ?? null,
            'is_correct' => (bool) ($this['is_correct'] ?? false),
            'correct_response_id' => $this['correct_response_id'],
            'feedback' => $this['feedback'] ?? null,
        ];
    }
}
