<?php

namespace App\Http\Resources\Scenarios;

use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserScenarioAttemptResource extends JsonResource
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
            'user_id' => $this->user_id,
            'scenario_id' => $this->scenario_id,
            'status' => $this->status,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'scenario' => new ScenarioResource($this->whenLoaded('scenario')),
            'user_scenario_question_answers' => UserScenarioQuestionAnswerResource::collection($this->whenLoaded('userScenarioQuestionAnswers')),
        ];
    }
}

