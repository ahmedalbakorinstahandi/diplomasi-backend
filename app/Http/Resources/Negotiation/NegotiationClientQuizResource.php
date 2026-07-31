<?php

namespace App\Http\Resources\Negotiation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CLIENT-SAFE quiz payload for START endpoints only.
 * Accepts the attempt service's `client` quiz array (already stripped).
 * Never accepts/serializes correct_response_id.
 */
class NegotiationClientQuizResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'seed' => $this['seed'] ?? null,
            'questions' => NegotiationClientQuizQuestionResource::collection(
                collect($this['questions'] ?? [])
            )->resolve(),
        ];
    }
}
