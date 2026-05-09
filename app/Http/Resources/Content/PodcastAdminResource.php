<?php

namespace App\Http\Resources\Content;

use App\Services\MediaUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full-detail resource used exclusively by the admin/dashboard.
 *
 * Exposes every field including internal paths, draft state, and timestamps.
 * Do NOT use this resource in the user-facing API.
 */
class PodcastAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Content\Podcast $podcast */
        $podcast = $this->resource;

        return [
            'id'                    => $podcast->id,
            'title'                 => $podcast->title,
            'slug'                  => $podcast->slug,
            'description'           => $podcast->description,

            // Media — resolved to full URLs
            'cover_image'           => $podcast->cover_image
                ? MediaUrlService::toUrl($podcast->cover_image)
                : null,
            'cover_image_raw'       => $podcast->cover_image,

            // Audio — stored path (internal) and resolved URL
            'audio_path'            => $podcast->audio_path,
            'audio_url_raw'         => $podcast->audio_url,
            'audio_url'             => $podcast->audio_url
                ? MediaUrlService::toUrl($podcast->audio_url)
                : ($podcast->audio_path
                    ? MediaUrlService::toUrl($podcast->audio_path)
                    : null),

            // Metadata
            'duration_seconds'      => $podcast->duration_seconds,
            'order_index'           => $podcast->order_index,
            'course_id'             => $podcast->course_id,

            // Flags
            'is_published'          => (bool) $podcast->is_published,
            'is_free'               => (bool) $podcast->is_free,
            'requires_subscription' => (bool) $podcast->requires_subscription,
            'allow_download'        => (bool) $podcast->allow_download,

            // Timestamps
            'published_at'          => $podcast->published_at,
            'created_at'            => $podcast->created_at,
            'updated_at'            => $podcast->updated_at,
            'deleted_at'            => $podcast->deleted_at,

            // Relations
            'course'                => $podcast->whenLoaded('course', fn () => [
                'id'   => $podcast->course->id,
                'name' => $podcast->course->name ?? $podcast->course->title ?? null,
            ]),
        ];
    }
}
