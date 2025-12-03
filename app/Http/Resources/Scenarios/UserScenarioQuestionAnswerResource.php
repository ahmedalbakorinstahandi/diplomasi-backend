<?php

namespace App\Http\Resources\Scenarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserScenarioQuestionAnswerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_id' => $this->question_id,
            'attempt_id' => $this->attempt_id,
            'step_index' => $this->step_index,
            'answered_at' => $this->answered_at,
            'time_spent' => $this->time_spent,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'scenario_question' => new ScenarioQuestionResource($this->whenLoaded('scenarioQuestion')),
            'user_scenario_attempt' => new UserScenarioAttemptResource($this->whenLoaded('userScenarioAttempt')),
            'user_scenario_answer_options' => UserScenarioAnswerOptionResource::collection($this->whenLoaded('userScenarioAnswerOptions')),
        ];
    }
}

