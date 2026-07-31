<?php

namespace App\Http\Resources\Negotiation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NegotiationResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'style' => $this->style,
            'response_text' => $this->response_text,
            'explanation' => $this->explanation,
        ];
    }
}
