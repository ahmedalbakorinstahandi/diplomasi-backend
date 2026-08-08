<?php

namespace App\Http\Resources\AiNegotiator;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiNegotiatorSessionListItemAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => trim(($this->user->first_name ?? '') . ' ' . ($this->user->last_name ?? '')),
                ];
            }),
            'session_state' => $this->session_state,
            'session_type' => $this->session_type,
            'difficulty' => $this->difficulty,
            'evaluation_score' => $this->when(
                $this->relationLoaded('evaluation'),
                fn () => $this->evaluation ? (int) $this->evaluation->overall_score : null
            ),
            'created_at' => $this->created_at,
        ];
    }
}
