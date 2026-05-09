<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Content\PodcastPermission;
use App\Http\Requests\Content\CreatePodcastRequest;
use App\Http\Requests\Content\ReOrderPodcastRequest;
use App\Http\Requests\Content\UpdatePodcastRequest;
use App\Http\Resources\Content\PodcastAdminResource;
use App\Http\Services\Content\PodcastAdminService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PodcastAdminController extends Controller
{
    public function __construct(
        protected PodcastAdminService $podcastAdminService,
    ) {}

    // ── List ──────────────────────────────────────────────────────────────────────

    public function index(Request $request, ?string $message = null): JsonResponse
    {
        PodcastPermission::canView();

        $podcasts = $this->podcastAdminService->index($request->all());

        return ResponseService::response([
            'success'  => true,
            'message'  => $message,
            'data'     => $podcasts,
            'meta'     => true,
            'resource' => PodcastAdminResource::class,
            'status'   => 200,
        ]);
    }

    // ── Single ────────────────────────────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        PodcastPermission::canView();

        $withTrashed = request()->boolean('with_trashed');
        $podcast     = $this->podcastAdminService->show($id, $withTrashed);

        PodcastPermission::canShow($podcast);

        return ResponseService::response([
            'success'  => true,
            'data'     => $podcast,
            'resource' => PodcastAdminResource::class,
            'status'   => 200,
        ]);
    }

    // ── Create ─────────────────────────────────────────────────────────────────────

    public function create(CreatePodcastRequest $request): JsonResponse
    {
        PodcastPermission::canCreate();

        $podcast = $this->podcastAdminService->create($request->validated());

        return ResponseService::response([
            'success'  => true,
            'data'     => $podcast,
            'message'  => 'messages.podcast.created',
            'resource' => PodcastAdminResource::class,
            'status'   => 201,
        ]);
    }

    // ── Update ─────────────────────────────────────────────────────────────────────

    public function update(UpdatePodcastRequest $request, int $id): JsonResponse
    {
        PodcastPermission::canUpdate();

        $podcast = $this->podcastAdminService->show($id);
        $podcast = $this->podcastAdminService->update($request->validated(), $podcast);

        return ResponseService::response([
            'success'  => true,
            'data'     => $podcast,
            'message'  => 'messages.podcast.updated',
            'resource' => PodcastAdminResource::class,
            'status'   => 200,
        ]);
    }

    // ── Publish / Unpublish ────────────────────────────────────────────────────────

    public function togglePublish(int $id): JsonResponse
    {
        PodcastPermission::canPublish();

        $podcast = $this->podcastAdminService->show($id);
        $podcast = $this->podcastAdminService->togglePublish($podcast);

        $message = $podcast->is_published
            ? 'messages.podcast.published'
            : 'messages.podcast.unpublished';

        return ResponseService::response([
            'success'  => true,
            'data'     => $podcast,
            'message'  => $message,
            'resource' => PodcastAdminResource::class,
            'status'   => 200,
        ]);
    }

    // ── Soft delete ────────────────────────────────────────────────────────────────

    public function delete(int $id): JsonResponse
    {
        PodcastPermission::canDelete();

        $podcast = $this->podcastAdminService->show($id);
        $this->podcastAdminService->delete($podcast);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.podcast.deleted',
            'status'  => 200,
        ]);
    }

    // ── Restore ────────────────────────────────────────────────────────────────────

    public function restore(int $id): JsonResponse
    {
        PodcastPermission::canRestore();

        $podcast = $this->podcastAdminService->restore($id);

        return ResponseService::response([
            'success'  => true,
            'data'     => $podcast,
            'message'  => 'messages.podcast.restored',
            'resource' => PodcastAdminResource::class,
            'status'   => 200,
        ]);
    }

    // ── Reorder ────────────────────────────────────────────────────────────────────

    public function reorder(int $id, ReOrderPodcastRequest $request): JsonResponse
    {
        PodcastPermission::canReorder();

        $podcast = $this->podcastAdminService->show($id);
        $this->podcastAdminService->reorder($podcast, $request->validated());

        return $this->index(request(), 'messages.podcast.reordered');
    }

    // ── Statistics ─────────────────────────────────────────────────────────────────

    public function stats(int $id): JsonResponse
    {
        PodcastPermission::canView();

        $podcast = $this->podcastAdminService->show($id);
        $stats   = $this->podcastAdminService->stats($podcast);

        return ResponseService::response([
            'success' => true,
            'data'    => $stats,
            'status'  => 200,
        ]);
    }

    public function globalStats(): JsonResponse
    {
        PodcastPermission::canView();

        $stats = $this->podcastAdminService->globalStats();

        return ResponseService::response([
            'success' => true,
            'data'    => $stats,
            'status'  => 200,
        ]);
    }
}
