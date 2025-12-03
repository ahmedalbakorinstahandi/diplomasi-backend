<?php

namespace App\Http\Resources\Scenarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScenarioQuestionResource extends JsonResource
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
            'scenario_id' => $this->scenario_id,
            'code' => $this->code,
            'type' => $this->type,
            'question_text' => $this->question_text,
            'attached_path' => $this->attached_path,
            'explanation' => $this->explanation,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'scenario' => new ScenarioResource($this->whenLoaded('scenario')),
            'scenario_question_options' => ScenarioQuestionOptionResource::collection($this->whenLoaded('scenarioQuestionOptions')),
            'previous_questions' => ScenarioQuestionOptionResource::collection($this->whenLoaded('previousQuestions')),
            'user_scenario_question_answers' => UserScenarioQuestionAnswerResource::collection($this->whenLoaded('userScenarioQuestionAnswers')),
            'starting_scenarios' => ScenarioResource::collection($this->whenLoaded('startingScenarios')),
        ];
    }
}

