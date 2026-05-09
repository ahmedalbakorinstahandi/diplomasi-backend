<?php

namespace App\Http\Resources\Content;

use App\Http\Services\Content\PodcastService;
use App\Services\MediaUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PodcastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Content\Podcast $podcast */
        $podcast = $this->resource;

        $podcastService = app(PodcastService::class);
        $user           = $request->user();
        $lock           = $podcastService->isLocked($podcast, $user);

        $progressRow = $podcast->relationLoaded('userPodcastProgress')
            ? $podcast->userPodcastProgress->first()
            : null;

        $isFavorite = false;
        if ($podcast->relationLoaded('userPodcastFavorites')) {
            $isFavorite = $podcast->userPodcastFavorites->isNotEmpty();
        }

        return [
            'id'                    => $podcast->id,
            'title'                 => $podcast->title,
            'description'           => $podcast->description !== null
                ? Str::limit(strip_tags($podcast->description), 240)
                : null,
            'cover_image'           => $podcast->cover_image
                ? MediaUrlService::toUrl($podcast->cover_image)
                : null,
            'duration_seconds'      => $podcast->duration_seconds,
            'is_free'               => (bool) $podcast->is_free,
            'requires_subscription' => (bool) $podcast->requires_subscription,
            'allow_download'        => (bool) $podcast->allow_download,
            'is_locked'             => $lock['is_locked'],
            'lock_reason'           => $lock['lock_reason'],
            'progress'              => $progressRow ? [
                'position_seconds'    => (int) $progressRow->position_seconds,
                'progress_percentage' => (float) $progressRow->progress_percentage,
                'is_completed'        => (bool) $progressRow->is_completed,
                'last_played_at'      => $progressRow->last_played_at,
            ] : [
                'position_seconds'    => 0,
                'progress_percentage' => 0.0,
                'is_completed'        => false,
                'last_played_at'      => null,
            ],
            'is_favorite'           => $isFavorite,
        ];
    }
}
