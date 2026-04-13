<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonVedioResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $courseTitle = $this->level?->course?->title;
        $levelNumber = $this->level?->level_number;
        $lessonTitle = $this->title;

        $generatedTitle = $lessonTitle;
        if (!empty($courseTitle) && !empty($levelNumber) && !empty($lessonTitle)) {
            $generatedTitle = "{$courseTitle} {$levelNumber}: {$lessonTitle}";
        }

        return [
            'id' => $this->id,
            'title' => $generatedTitle,
            'video_url' => $this->video_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
