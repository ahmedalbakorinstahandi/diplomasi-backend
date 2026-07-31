<?php

namespace App\Http\Controllers\Negotiation;

use App\Http\Controllers\Controller;
use App\Http\Resources\Negotiation\NegotiationLevelResource;
use App\Http\Resources\Negotiation\NegotiationSituationListItemResource;
use App\Http\Services\Negotiation\NegotiationLibraryService;
use App\Models\Users\User;
use App\Services\MessageService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class NegotiationLevelController extends Controller
{
    public function __construct(
        protected NegotiationLibraryService $libraryService,
    ) {}

    public function index(Request $request)
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $result = $this->libraryService->listLevels($user->id);
        NegotiationLevelResource::setProgressDataCache($result['progress']);

        return ResponseService::response([
            'success' => true,
            'data' => NegotiationLevelResource::collection($result['levels'])->resolve(),
            'status' => 200,
        ]);
    }

    public function show(int $level)
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $result = $this->libraryService->showLevel($level, $user->id);
        NegotiationLevelResource::setProgressDataCache($result['progress']);

        $payload = (new NegotiationLevelResource($result['level']))
            ->asDetail()
            ->resolve();

        return ResponseService::response([
            'success' => true,
            'data' => $payload,
            'status' => 200,
        ]);
    }

    public function situations(int $level)
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $result = $this->libraryService->listSituationsForLevel($level, $user->id);

        NegotiationSituationListItemResource::setAccessCache($result['access']);
        NegotiationSituationListItemResource::setNoteIdsCache($result['note_ids']);

        return ResponseService::response([
            'success' => true,
            'data' => NegotiationSituationListItemResource::collection($result['situations'])->resolve(),
            'status' => 200,
        ]);
    }
}
