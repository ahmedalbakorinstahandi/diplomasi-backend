<?php

namespace App\Http\Resources\Negotiation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NegotiationSituationResource extends JsonResource
{
    protected ?string $accessStatus = null;

    protected ?string $noteText = null;

    public function withAccessStatus(string $accessStatus): self
    {
        $this->accessStatus = $accessStatus;

        return $this;
    }

    public function withNoteText(?string $noteText): self
    {
        $this->noteText = $noteText;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $responses = $this->whenLoaded('negotiationResponses', function () {
            // Stable style order for study view
            $order = ['gentle' => 0, 'diplomatic' => 1, 'firm' => 2];
            $sorted = $this->negotiationResponses->sortBy(
                fn ($r) => $order[$r->style] ?? 99
            )->values();

            return NegotiationResponseResource::collection($sorted);
        });

        return [
            'id' => $this->id,
            'negotiation_level_id' => $this->negotiation_level_id,
            'prompt_context' => $this->prompt_context,
            'prompt_text' => $this->prompt_text,
            'prompt_type' => $this->prompt_type,
            'insight' => $this->insight,
            'order_index' => $this->order_index,
            'is_free' => (bool) $this->is_free,
            'access_status' => $this->accessStatus,
            'note_text' => $this->noteText,
            'responses' => $responses,
        ];
    }
}
