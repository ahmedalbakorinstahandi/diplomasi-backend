<?php

namespace App\Http\Resources\Content;

use App\Http\Services\Content\PodcastService;
use Illuminate\Http\Request;

class PodcastDetailResource extends PodcastResource
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

        $base = parent::toArray($request);

        // Override description with full text (not truncated)
        $base['description']  = $podcast->description;
        $base['published_at'] = $podcast->published_at;

        // Playable URLs — null when locked or missing
        $base['stream_url']   = $podcastService->resolveAudioUrl($podcast, $user);
        $base['download_url'] = $podcastService->resolveDownloadUrl($podcast, $user);

        // Lets the app detect when the source file was replaced so offline copy can be refreshed
        $base['updated_at'] = $podcast->updated_at?->toIso8601String();

        return $base;
    }
}
