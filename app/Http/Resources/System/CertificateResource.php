<?php

namespace App\Http\Resources\System;

use App\Http\Resources\Learning\CourseResource;
use App\Http\Resources\Learning\LevelResource;
use App\Http\Resources\Users\UserResource;
use App\Models\Progress\UserLevelProgress;
use App\Models\Users\User;
use App\Services\Certificates\CertificateNameFormatterService;
use App\Services\MediaUrlService;
use App\Services\RequestContext;
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
        $base = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'level_id' => $this->level_id,
            'certificate_code' => $this->certificate_code,
            'issued_at' => $this->issued_at,
            'qr_code' => MediaUrlService::toUrl($this->qr_code),
            'pdf_url' => MediaUrlService::toUrl($this->pdf_url),
            'image_url' => MediaUrlService::toUrl($this->image_url),
            'template_path' => MediaUrlService::toUrl($this->template_path),
            'rendered_name' => $this->rendered_name,
            'rendered_date' => $this->rendered_date,
            'template_snapshot_path' => MediaUrlService::toUrl($this->template_snapshot_path),
            'verification_url' => config('app.url') . '/api/v1/general/certificates/verify/' . $this->certificate_code,
            'web_verification_url' => config('app.url') . '/certificates/verify/' . $this->certificate_code,
            'download_url' => $this->image_url ? config('app.url') . '/api/v1/user/certificates/' . $this->id . '/download' : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'course' => new CourseResource($this->whenLoaded('course')),
            'level' => new LevelResource($this->whenLoaded('level')),
        ];

        if (RequestContext::isDashboard() && $this->level_id) {
            $user = $this->relationLoaded('user') ? $this->user : User::find($this->user_id);
            $nameFormatter = app(CertificateNameFormatterService::class);
            $defaultName = $user
                ? $nameFormatter->format($user->first_name, $user->last_name)
                : (string) ($this->rendered_name ?? '');
            $completedAt = UserLevelProgress::where('user_id', $this->user_id)
                ->where('level_id', $this->level_id)
                ->value('completed_at');
            $issuedForDefault = $completedAt
                ? \Carbon\Carbon::parse($completedAt)
                : ($this->issued_at ?? now());
            $base['regeneration_defaults'] = [
                'rendered_name' => $defaultName,
                'issued_at' => $issuedForDefault->toIso8601String(),
            ];
        }

        return $base;
    }
}
