<?php

namespace App\Http\Resources\Learning;

use App\Http\Resources\Progress\UserLevelProgressResource;
use App\Http\Resources\Scenarios\ScenarioResource;
use App\Http\Resources\System\CertificateResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LevelResource extends JsonResource
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
            'course_id' => $this->course_id,
            'level_number' => $this->level_number,
            'title' => $this->title,
            'description' => $this->description,
            'is_published' => $this->is_published,
            'is_free' => $this->is_free,
            'has_certificate' => $this->has_certificate,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'course' => new CourseResource($this->whenLoaded('course')),
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
            'scenarios' => ScenarioResource::collection($this->whenLoaded('scenarios')),
            'level_track' => new LevelTrackResource($this->whenLoaded('levelTrack')),
            'user_level_progress' => UserLevelProgressResource::collection($this->whenLoaded('userLevelProgress')),
            'certificates' => CertificateResource::collection($this->whenLoaded('certificates')),
        ];
    }
}

