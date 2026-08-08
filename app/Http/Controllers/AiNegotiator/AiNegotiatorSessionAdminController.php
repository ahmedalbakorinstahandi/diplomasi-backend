<?php

namespace App\Http\Controllers\AiNegotiator;

use App\Http\Controllers\Controller;
use App\Http\Permissions\AiNegotiator\AiNegotiatorSessionPermission;
use App\Http\Resources\AiNegotiator\AiNegotiatorSessionAdminResource;
use App\Http\Resources\AiNegotiator\AiNegotiatorSessionListItemAdminResource;
use App\Http\Services\AiNegotiator\SessionService;
use App\Http\Services\AiNegotiator\StateMachine\SessionStateException;
use App\Models\AiNegotiator\AiNegotiatorSession;
use App\Services\MessageService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class AiNegotiatorSessionAdminController extends Controller
{
    public function __construct(
        protected SessionService $sessionService,
    ) {}

    public function index(Request $request)
    {
        AiNegotiatorSessionPermission::canView();

        $query = AiNegotiatorSession::query()
            ->with(['user', 'evaluation'])
            ->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->get('user_id'));
        }

        if ($request->filled('session_state')) {
            $query->where('session_state', $request->get('session_state'));
        }

        if ($request->filled('session_type')) {
            $query->where('session_type', $request->get('session_type'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->get('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->get('to'));
        }

        $sessions = $query->paginate((int) $request->get('per_page', 15));

        return ResponseService::response([
            'success' => true,
            'data' => $sessions,
            'meta' => true,
            'resource' => AiNegotiatorSessionListItemAdminResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $sessionId)
    {
        AiNegotiatorSessionPermission::canView();

        $session = AiNegotiatorSession::query()
            ->with(['user', 'messages', 'evaluation.scores.rubricItem'])
            ->find($sessionId);

        if (!$session) {
            MessageService::abort(404, 'messages.ai_negotiator.session_not_found');
        }

        return ResponseService::response([
            'success' => true,
            'data' => $session,
            'resource' => AiNegotiatorSessionAdminResource::class,
            'status' => 200,
        ]);
    }

    public function forceAbandon(int $sessionId)
    {
        AiNegotiatorSessionPermission::canManage();

        $session = AiNegotiatorSession::query()->find($sessionId);

        if (!$session) {
            MessageService::abort(404, 'messages.ai_negotiator.session_not_found');
        }

        try {
            $this->sessionService->abandonSession($session);
        } catch (SessionStateException $e) {
            MessageService::abort(409, 'messages.ai_negotiator.invalid_session_state');
        }

        $session = $session->fresh(['user', 'evaluation']);

        return ResponseService::response([
            'success' => true,
            'data' => $session,
            'message' => 'messages.ai_negotiator.session_abandoned',
            'resource' => AiNegotiatorSessionAdminResource::class,
            'status' => 200,
        ]);
    }

    public function destroy(int $sessionId)
    {
        AiNegotiatorSessionPermission::canManage();

        $session = AiNegotiatorSession::query()->find($sessionId);

        if (!$session) {
            MessageService::abort(404, 'messages.ai_negotiator.session_not_found');
        }

        $session->delete();

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.ai_negotiator.session_deleted',
            'status' => 200,
        ]);
    }
}
