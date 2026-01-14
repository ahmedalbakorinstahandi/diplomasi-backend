<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Learning\LevelPermission;
use App\Http\Requests\Learning\CreateLevelRequest;
use App\Http\Requests\Learning\ReOrderLevelRequest;
use App\Http\Requests\Learning\UpdateLevelRequest;
use App\Http\Resources\Learning\LevelResource;
use App\Http\Services\Learning\LevelService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class LevelController extends Controller
{
    protected $levelService;

    public function __construct(LevelService $levelService)
    {
        $this->levelService = $levelService;
    }

    public function index(Request $request, $message = null)
    {
        LevelPermission::canView();

        $levels = $this->levelService->index($request->all());
        
        // Get paginated results if query is paginated
        $perPage = $request->input('per_page', 20);
        if ($levels instanceof \Illuminate\Contracts\Pagination\Paginator) {
            $levelsPaginated = $levels;
        } else {
            $levelsPaginated = $levels->paginate($perPage);
        }

        // Load progress data in batch if user is authenticated
        $user = \App\Models\Users\User::auth();
        if ($user && $levelsPaginated->count() > 0) {
            $levelsList = $levelsPaginated->items();
            $trackProgressService = app(\App\Services\TrackProgressService::class);
            $progressData = $trackProgressService->loadProgressDataForLevels(collect($levelsList), $user->id);
            
            // Set cache for Resource to use
            LevelResource::setProgressDataCache($progressData);
        } else {
            // Clear cache if no user
            LevelResource::clearProgressDataCache();
        }

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $levelsPaginated,
            'meta' => true,
            'resource' => LevelResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        LevelPermission::canView();

        $level = $this->levelService->show($id);
        LevelPermission::canShow($level);

        // Load progress data if user is authenticated
        $user = \App\Models\Users\User::auth();
        if ($user) {
            $trackProgressService = app(\App\Services\TrackProgressService::class);
            $progressData = $trackProgressService->loadProgressDataForLevels(collect([$level]), $user->id);
            LevelResource::setProgressDataCache($progressData);
        } else {
            LevelResource::clearProgressDataCache();
        }

        return ResponseService::response([
            'success' => true,
            'data' => $level,
            'resource' => LevelResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateLevelRequest $request)
    {
        LevelPermission::canCreate();

        $level = $this->levelService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $level,
            'message' => 'messages.level.created',
            'status' => 201,
            'resource' => LevelResource::class,
        ]);
    }

    public function update(UpdateLevelRequest $request, int $id)
    {
        LevelPermission::canUpdate();

        $level = $this->levelService->show($id);

        $level = $this->levelService->update($request->validated(), $level);

        return ResponseService::response([
            'success' => true,
            'data' => $level,
            'message' => 'messages.level.updated',
            'status' => 200,
            'resource' => LevelResource::class,
        ]);
    }

    public function delete(int $id)
    {
        LevelPermission::canDelete();

        $level = $this->levelService->show($id);

        $this->levelService->delete($level);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.level.deleted',
            'status' => 200,
        ]);
    }

    public function reorder(int $id, ReOrderLevelRequest $request)
    {
        LevelPermission::canReorder();

        $level = $this->levelService->show($id);

        $level = $this->levelService->reorder($level, $request->validated());

        return $this->index(request(), 'messages.level.reordered');
    }
}
