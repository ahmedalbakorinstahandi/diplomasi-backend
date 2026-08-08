<?php

namespace App\Http\Resources\AiNegotiator;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiNegotiatorSessionAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_type' => $this->session_type,
            'session_state' => $this->session_state,
            'difficulty' => $this->difficulty,
            'training_mode' => $this->training_mode,
            'situation_type' => $this->situation_type,
            'intake_data' => $this->intake_data,
            'opponent_persona' => $this->opponent_persona,
            'started_at' => $this->started_at,
            'simulating_started_at' => $this->simulating_started_at,
            'completed_at' => $this->completed_at,
            'abandoned_at' => $this->abandoned_at,
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => trim(($this->user->first_name ?? '') . ' ' . ($this->user->last_name ?? '')),
                    'email' => $this->user->email,
                ];
            }),
            'messages' => AiNegotiatorMessageResource::collection($this->whenLoaded('messages')),
            'evaluation' => $this->whenLoaded('evaluation', function () {
                return $this->evaluation
                    ? new AiNegotiatorEvaluationResource($this->evaluation)
                    : null;
            }),
        ];
    }
}
