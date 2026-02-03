<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Learning\LevelTrackPermission;
use App\Http\Requests\Learning\CreateLevelTrackRequest;
use App\Http\Requests\Learning\ReOrderLevelTrackRequest;
use App\Http\Requests\Learning\UpdateLevelTrackRequest;
use App\Http\Resources\Learning\LevelTrackResource;
use App\Http\Services\Learning\LevelTrackService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class LevelTrackController extends Controller
{
    protected $levelTrackService;

    public function __construct(LevelTrackService $levelTrackService)
    {
        $this->levelTrackService = $levelTrackService;
    }

    public function index(Request $request, $message = null)
    {
        // LevelTrackPermission::canView();

        $query = $this->levelTrackService->index($request->all());
        
        // Get paginated results
        $perPage = $request->input(key: 'per_page', default: 20);
        if ($query instanceof \Illuminate\Contracts\Pagination\Paginator) {
            $levelTracksPaginated = $query;
        } else {
            $levelTracksPaginated = $query->paginate($perPage);
        }
        
        // Load progress data in batch if user is authenticated
        $user = \App\Models\Users\User::auth();
        if ($user && $levelTracksPaginated->count() > 0) {
            $levelTracks = $levelTracksPaginated->items();
            $trackProgressService = app(\App\Services\TrackProgressService::class);
            $progressData = $trackProgressService->loadProgressDataForTracks(collect($levelTracks), $user->id);
            
            // Set cache for Resource to use
            LevelTrackResource::setProgressDataCache($progressData);
        } else {
            // Clear cache if no user
            LevelTrackResource::clearProgressDataCache();
        }

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $levelTracksPaginated,
            'meta' => true,
            'resource' => LevelTrackResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        LevelTrackPermission::canView();

        $levelTrack = $this->levelTrackService->show($id);
        LevelTrackPermission::canShow($levelTrack);

        return ResponseService::response([
            'success' => true,
            'data' => $levelTrack,
            'resource' => LevelTrackResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateLevelTrackRequest $request)
    {
        LevelTrackPermission::canCreate();

        $levelTrack = $this->levelTrackService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $levelTrack,
            'message' => 'messages.level_track.created',
            'status' => 201,
            'resource' => LevelTrackResource::class,
        ]);
    }

    public function update(UpdateLevelTrackRequest $request, int $id)
    {
        LevelTrackPermission::canUpdate();

        $levelTrack = $this->levelTrackService->show($id);

        $levelTrack = $this->levelTrackService->update($request->validated(), $levelTrack);

        return ResponseService::response([
            'success' => true,
            'data' => $levelTrack,
            'message' => 'messages.level_track.updated',
            'status' => 200,
            'resource' => LevelTrackResource::class,
        ]);
    }

    public function delete(int $id)
    {
        LevelTrackPermission::canDelete();

        $levelTrack = $this->levelTrackService->show($id);

        $this->levelTrackService->delete($levelTrack);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.level_track.deleted',
            'status' => 200,
        ]);
    }

    public function reorder(int $id, ReOrderLevelTrackRequest $request)
    {
        LevelTrackPermission::canReorder();

        $levelTrack = $this->levelTrackService->show($id);

        $levelTrack = $this->levelTrackService->reorder($levelTrack, $request->validated());

        return $this->index(request(), 'messages.level_track.reordered');
    }

    public function syncForLevel(int $levelId)
    {
        LevelTrackPermission::canUpdate();

        $query = $this->levelTrackService->syncForLevel($levelId);
        
        // Get all results (syncForLevel returns query)
        $levelTracks = $query->get();
        
        // Load progress data in batch if user is authenticated
        $user = \App\Models\Users\User::auth();
        if ($user && $levelTracks->isNotEmpty()) {
            $trackProgressService = app(\App\Services\TrackProgressService::class);
            $progressData = $trackProgressService->loadProgressDataForTracks($levelTracks, $user->id);
            
            // Set cache for Resource to use
            LevelTrackResource::setProgressDataCache($progressData);
        } else {
            LevelTrackResource::clearProgressDataCache();
        }

        return ResponseService::response([
            'success' => true,
            'data' => $query,
            'message' => 'messages.level_track.synced',
            'status' => 200,
            'meta' => true,
            'resource' => LevelTrackResource::class,
        ]);
    }
}

