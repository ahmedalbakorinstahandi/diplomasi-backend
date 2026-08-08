<?php

namespace App\Http\Resources\AiNegotiator;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiNegotiatorMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'type' => $this->type,
            'content' => $this->content,
            'state_at_time' => $this->state_at_time,
            'order_index' => (int) $this->order_index,
            'created_at' => $this->created_at,
        ];
    }
}
