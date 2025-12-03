<?php

namespace App\Http\Resources\Scenarios;

use App\Http\Resources\Learning\LevelResource;
use App\Http\Resources\Learning\LevelTrackResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScenarioResource extends JsonResource
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
            'level_id' => $this->level_id,
            'title' => $this->title,
            'description' => $this->description,
            'is_published' => $this->is_published,
            'is_free' => $this->is_free,
            'start_question_id' => $this->start_question_id,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'level' => new LevelResource($this->whenLoaded('level')),
            'start_question' => new ScenarioQuestionResource($this->whenLoaded('startQuestion')),
            'scenario_questions' => ScenarioQuestionResource::collection($this->whenLoaded('scenarioQuestions')),
            'user_scenario_attempts' => UserScenarioAttemptResource::collection($this->whenLoaded('userScenarioAttempts')),
            'level_tracks' => LevelTrackResource::collection($this->whenLoaded('levelTracks')),
        ];
    }
}

