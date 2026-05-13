<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\PodcastProgressRequest;
use App\Http\Resources\Content\PodcastDetailResource;
use App\Http\Resources\Content\PodcastResource;
use App\Http\Services\Content\PodcastService;
use App\Models\Content\Podcast;
use App\Models\Users\User;
use App\Services\MessageService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class PodcastController extends Controller
{
    public function __construct(
        protected PodcastService $podcastService
    ) {}

    public function index(Request $request)
    {
        $user = $this->resolvePodcastViewer($request);
        $this->attachViewerToRequest($request, $user);
        $podcasts = $this->podcastService->index($request->all(), $user);

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
        $user = $this->resolvePodcastViewer($request);
        $this->attachViewerToRequest($request, $user);
        $podcast = $this->podcastService->show($id, $user);

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

    /**
     * List/show podcast routes are public (guests can browse), so Laravel does not
     * run auth middleware and $request->user() is null even when the app sends Bearer token.
     * Resolve the Sanctum user from the personal access token so favorites & progress load.
     */
    protected function resolvePodcastViewer(Request $request): ?User
    {
        $auth = $request->user();
        if ($auth instanceof User) {
            return $auth;
        }

        $plain = $request->bearerToken();
        if ($plain === null || $plain === '') {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($plain);
        if ($accessToken === null) {
            return null;
        }

        $model = $accessToken->tokenable;

        return $model instanceof User ? $model : null;
    }

    /**
     * So JsonResources can use $request->user() for lock / subscription checks.
     */
    protected function attachViewerToRequest(Request $request, ?User $user): void
    {
        if ($user === null) {
            return;
        }

        $request->setUserResolver(static fn () => $user);
    }
}
