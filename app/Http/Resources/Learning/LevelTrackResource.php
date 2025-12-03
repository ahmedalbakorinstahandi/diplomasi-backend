<?php

namespace App\Http\Resources\Learning;

use App\Http\Resources\Scenarios\ScenarioResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LevelTrackResource extends JsonResource
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
            'trackable_id' => $this->trackable_id,
            'trackable_type' => $this->trackable_type,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'level' => new LevelResource($this->whenLoaded('level')),
            'trackable' => $this->when($this->relationLoaded('trackable'), function () {
                if ($this->trackable_type === 'App\\Models\\Learning\\Lesson') {
                    return new LessonResource($this->trackable);
                } elseif ($this->trackable_type === 'App\\Models\\Scenarios\\Scenario') {
                    return new ScenarioResource($this->trackable);
                }
                return null;
            }),
        ];
    }
}

