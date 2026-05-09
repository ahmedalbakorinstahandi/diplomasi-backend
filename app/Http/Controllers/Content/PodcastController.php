<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\PodcastProgressRequest;
use App\Http\Resources\Content\PodcastDetailResource;
use App\Http\Resources\Content\PodcastResource;
use App\Http\Services\Content\PodcastService;
use App\Models\Content\Podcast;
use App\Services\MessageService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class PodcastController extends Controller
{
    public function __construct(
        protected PodcastService $podcastService
    ) {}

    public function index(Request $request)
    {
        $podcasts = $this->podcastService->index($request->all(), $request->user());

        return ResponseService::response([
            'success'  => true,
            'data'     => $podcasts,
            'meta'     => true,
            'resource' => PodcastResource::class,
            'status'   => 200,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $podcast = $this->podcastService->show($id, $request->user());

        return ResponseService::response([
            'success'  => true,
            'data'     => $podcast,
            'resource' => PodcastDetailResource::class,
            'status'   => 200,
        ]);
    }

    public function updateProgress(PodcastProgressRequest $request, int $id)
    {
        $podcast = Podcast::query()->published()->where('id', $id)->first();
        if (! $podcast) {
            MessageService::abort(404, 'messages.podcast.not_found');
        }

        $progress = $this->podcastService->updateProgress($podcast, $request->user(), $request->validated());

        return ResponseService::response([
            'success' => true,
            'data'    => [
                'id'                  => $progress->id,
                'podcast_id'          => $progress->podcast_id,
                'position_seconds'    => (int) $progress->position_seconds,
                'duration_seconds'    => $progress->duration_seconds !== null ? (int) $progress->duration_seconds : null,
                'progress_percentage' => (float) $progress->progress_percentage,
                'is_completed'        => (bool) $progress->is_completed,
                'last_played_at'      => $progress->last_played_at,
                'completed_at'        => $progress->completed_at,
            ],
            'status'  => 200,
        ]);
    }

    public function addFavorite(Request $request, int $id)
    {
        $podcast = Podcast::query()->published()->where('id', $id)->first();
        if (! $podcast) {
            MessageService::abort(404, 'messages.podcast.not_found');
        }
        $this->podcastService->toggleFavorite($podcast, $request->user(), true);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.podcast.favorite_added',
            'status'  => 200,
        ]);
    }

    public function removeFavorite(Request $request, int $id)
    {
        $podcast = Podcast::query()->published()->where('id', $id)->first();
        if (! $podcast) {
            MessageService::abort(404, 'messages.podcast.not_found');
        }
        $this->podcastService->toggleFavorite($podcast, $request->user(), false);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.podcast.favorite_removed',
            'status'  => 200,
        ]);
    }
}
