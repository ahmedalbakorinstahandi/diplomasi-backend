<?php

namespace App\Http\Controllers\Negotiation;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Negotiation\NegotiationSituationPermission;
use App\Http\Requests\Negotiation\CreateNegotiationSituationRequest;
use App\Http\Requests\Negotiation\ReOrderNegotiationSituationRequest;
use App\Http\Requests\Negotiation\UpdateNegotiationSituationRequest;
use App\Http\Resources\Negotiation\NegotiationSituationAdminResource;
use App\Http\Services\Negotiation\NegotiationSituationAdminService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class NegotiationSituationAdminController extends Controller
{
    public function __construct(
        protected NegotiationSituationAdminService $negotiationSituationAdminService,
    ) {}

    public function index(Request $request, $message = null)
    {
        NegotiationSituationPermission::canView();

        $situations = $this->negotiationSituationAdminService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $situations,
            'meta' => true,
            'resource' => NegotiationSituationAdminResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        NegotiationSituationPermission::canView();

        $situation = $this->negotiationSituationAdminService->show($id);

        return ResponseService::response([
            'success' => true,
            'data' => $situation,
            'resource' => NegotiationSituationAdminResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateNegotiationSituationRequest $request)
    {
        NegotiationSituationPermission::canCreate();

        $situation = $this->negotiationSituationAdminService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $situation,
            'message' => 'messages.negotiation_situation.created',
            'status' => 201,
            'resource' => NegotiationSituationAdminResource::class,
        ]);
    }

    public function update(UpdateNegotiationSituationRequest $request, int $id)
    {
        NegotiationSituationPermission::canUpdate();

        $situation = $this->negotiationSituationAdminService->show($id);
        $situation = $this->negotiationSituationAdminService->update($request->validated(), $situation);

        return ResponseService::response([
            'success' => true,
            'data' => $situation,
            'message' => 'messages.negotiation_situation.updated',
            'status' => 200,
            'resource' => NegotiationSituationAdminResource::class,
        ]);
    }

    public function delete(int $id)
    {
        NegotiationSituationPermission::canDelete();

        $situation = $this->negotiationSituationAdminService->show($id);
        $this->negotiationSituationAdminService->delete($situation);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.negotiation_situation.deleted',
            'status' => 200,
        ]);
    }

    public function reorder(int $id, ReOrderNegotiationSituationRequest $request)
    {
        NegotiationSituationPermission::canReorder();

        $situation = $this->negotiationSituationAdminService->show($id);
        $this->negotiationSituationAdminService->reorder($situation, $request->validated());

        return $this->index(request(), 'messages.negotiation_situation.reordered');
    }
}
