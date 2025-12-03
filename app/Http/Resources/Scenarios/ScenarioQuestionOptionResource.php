<?php

namespace App\Http\Resources\Scenarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScenarioQuestionOptionResource extends JsonResource
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
            'option_text' => $this->option_text,
            'next_question_id' => $this->next_question_id,
            'attached_path' => $this->attached_path,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'scenario_question' => new ScenarioQuestionResource($this->whenLoaded('scenarioQuestion')),
            'next_question' => new ScenarioQuestionResource($this->whenLoaded('nextQuestion')),
            'user_scenario_answer_options' => UserScenarioAnswerOptionResource::collection($this->whenLoaded('userScenarioAnswerOptions')),
        ];
    }
}

