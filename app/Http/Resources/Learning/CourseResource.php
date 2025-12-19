<?php

namespace App\Http\Resources\Learning;

use App\Http\Resources\Progress\UserCourseResource;
use App\Http\Resources\System\CertificateResource;
use App\Services\MediaUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => MediaUrlService::toUrl($this->image_url),
            'is_published' => $this->is_published,
            'is_free' => $this->is_free,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'levels' => LevelResource::collection($this->whenLoaded('levels')),
            'user_courses' => UserCourseResource::collection($this->whenLoaded('userCourses')),
            'certificates' => CertificateResource::collection($this->whenLoaded('certificates')),
        ];
    }
}

