<?php

namespace App\Http\Resources\AiNegotiator;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiNegotiatorEvaluationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'overall_score' => (int) $this->overall_score,
            'summary' => $this->summary,
            'best_line' => $this->best_line,
            'weakest_line' => $this->weakest_line,
            'biggest_mistake' => $this->biggest_mistake,
            'quick_concession' => (bool) $this->quick_concession,
            'sensitive_info_leaked' => (bool) $this->sensitive_info_leaked,
            'good_questions' => (bool) $this->good_questions,
            'suggested_alternative_response' => $this->suggested_alternative_response,
            'retry_exercise' => $this->retry_exercise,
            'suggested_next_difficulty' => $this->suggested_next_difficulty,
            'scores' => $this->whenLoaded('scores', function () {
                return $this->scores->map(static function ($score) {
                    return [
                        'code' => $score->rubricItem?->code,
                        'title' => $score->rubricItem?->title,
                        'score' => (int) $score->score,
                        'max_score' => (int) $score->max_score,
                    ];
                })->values()->all();
            }),
            'created_at' => $this->created_at,
        ];
    }
}
