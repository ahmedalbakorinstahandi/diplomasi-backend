<?php

namespace App\Http\Controllers\Negotiation;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Negotiation\NegotiationLevelPermission;
use App\Http\Requests\Negotiation\CreateNegotiationLevelRequest;
use App\Http\Requests\Negotiation\ReOrderNegotiationLevelRequest;
use App\Http\Requests\Negotiation\UpdateNegotiationLevelRequest;
use App\Http\Resources\Negotiation\NegotiationLevelAdminResource;
use App\Http\Services\Negotiation\NegotiationLevelAdminService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class NegotiationLevelAdminController extends Controller
{
    public function __construct(
        protected NegotiationLevelAdminService $negotiationLevelAdminService,
    ) {}

    public function index(Request $request, $message = null)
    {
        NegotiationLevelPermission::canView();

        $levels = $this->negotiationLevelAdminService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $levels,
            'meta' => true,
            'resource' => NegotiationLevelAdminResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        NegotiationLevelPermission::canView();

        $level = $this->negotiationLevelAdminService->show($id);

        return ResponseService::response([
            'success' => true,
            'data' => $level,
            'resource' => NegotiationLevelAdminResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateNegotiationLevelRequest $request)
    {
        NegotiationLevelPermission::canCreate();

        $level = $this->negotiationLevelAdminService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $level,
            'message' => 'messages.negotiation_level.created',
            'status' => 201,
            'resource' => NegotiationLevelAdminResource::class,
        ]);
    }

    public function update(UpdateNegotiationLevelRequest $request, int $id)
    {
        NegotiationLevelPermission::canUpdate();

        $level = $this->negotiationLevelAdminService->show($id);
        $level = $this->negotiationLevelAdminService->update($request->validated(), $level);

        return ResponseService::response([
            'success' => true,
            'data' => $level,
            'message' => 'messages.negotiation_level.updated',
            'status' => 200,
            'resource' => NegotiationLevelAdminResource::class,
        ]);
    }

    public function delete(int $id)
    {
        NegotiationLevelPermission::canDelete();

        $level = $this->negotiationLevelAdminService->show($id);
        $this->negotiationLevelAdminService->delete($level);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.negotiation_level.deleted',
            'status' => 200,
        ]);
    }

    public function reorder(int $id, ReOrderNegotiationLevelRequest $request)
    {
        NegotiationLevelPermission::canReorder();

        $level = $this->negotiationLevelAdminService->show($id);
        $this->negotiationLevelAdminService->reorder($level, $request->validated());

        return $this->index(request(), 'messages.negotiation_level.reordered');
    }
}
