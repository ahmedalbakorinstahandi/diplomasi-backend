<?php

namespace App\Http\Resources\Negotiation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Archive list item (no quiz content).
 */
class NegotiationAttemptArchiveItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'score' => $this->score !== null ? (float) $this->score : null,
            'correct_count' => (int) $this->correct_count,
            'total_questions' => (int) $this->total_questions,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
        ];
    }
}
