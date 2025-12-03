<?php

namespace App\Http\Resources\System;

use App\Http\Resources\Learning\CourseResource;
use App\Http\Resources\Learning\LevelResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
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
            'course_id' => $this->course_id,
            'level_id' => $this->level_id,
            'certificate_code' => $this->certificate_code,
            'issued_at' => $this->issued_at,
            'qr_code' => $this->qr_code,
            'pdf_url' => $this->pdf_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'course' => new CourseResource($this->whenLoaded('course')),
            'level' => new LevelResource($this->whenLoaded('level')),
        ];
    }
}

