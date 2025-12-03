<?php

namespace App\Http\Resources\Scenarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserScenarioAnswerOptionResource extends JsonResource
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
            'user_answer_id' => $this->user_answer_id,
            'option_id' => $this->option_id,
            'created_at' => $this->created_at,
            
            // Relationships
            'user_scenario_question_answer' => new UserScenarioQuestionAnswerResource($this->whenLoaded('userScenarioQuestionAnswer')),
            'scenario_question_option' => new ScenarioQuestionOptionResource($this->whenLoaded('scenarioQuestionOption')),
        ];
    }
}

