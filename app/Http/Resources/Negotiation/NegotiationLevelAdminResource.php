<?php

namespace App\Http\Resources\Negotiation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin/dashboard-only resource for negotiation levels.
 * Do NOT use on learner-facing endpoints (no access_status / progress).
 */
class NegotiationLevelAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'how_to_study' => $this->how_to_study,
            'order_index' => $this->order_index,
            'is_published' => (bool) $this->is_published,
            'situations_count' => (int) ($this->negotiation_situations_count ?? 0),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
