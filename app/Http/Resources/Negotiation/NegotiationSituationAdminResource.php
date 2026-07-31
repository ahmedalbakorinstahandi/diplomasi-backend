<?php

namespace App\Http\Resources\Negotiation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin/dashboard-only resource for negotiation situations + nested responses.
 * Do NOT use on learner-facing endpoints (no access_status / progress).
 */
class NegotiationSituationAdminResource extends JsonResource
{
    private const STYLE_ORDER = [
        'gentle' => 0,
        'diplomatic' => 1,
        'firm' => 2,
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $responses = [];

        if ($this->relationLoaded('negotiationResponses')) {
            $responses = $this->negotiationResponses
                ->sortBy(fn ($response) => self::STYLE_ORDER[$response->style] ?? 99)
                ->values()
                ->map(fn ($response) => [
                    'id' => $response->id,
                    'style' => $response->style,
                    'response_text' => $response->response_text,
                    'explanation' => $response->explanation,
                ])
                ->all();
        }

        return [
            'id' => $this->id,
            'negotiation_level_id' => $this->negotiation_level_id,
            'prompt_context' => $this->prompt_context,
            'prompt_text' => $this->prompt_text,
            'prompt_type' => $this->prompt_type,
            'insight' => $this->insight,
            'is_free' => (bool) $this->is_free,
            'is_published' => (bool) $this->is_published,
            'order_index' => $this->order_index,
            'responses' => $responses,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
