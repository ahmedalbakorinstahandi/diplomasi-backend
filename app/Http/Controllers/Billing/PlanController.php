<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Billing\PlanPermission;
use App\Http\Requests\Billing\CreatePlanRequest;
use App\Http\Requests\Billing\ReOrderPlanRequest;
use App\Http\Requests\Billing\UpdatePlanRequest;
use App\Http\Resources\Billing\PlanResource;
use App\Http\Services\Billing\PlanService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    protected $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }

    public function index(Request $request, $message = null)
    {
        PlanPermission::canView();

        $plans = $this->planService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $plans,
            'meta' => true,
            'resource' => PlanResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        PlanPermission::canView();

        $plan = $this->planService->show($id);
        PlanPermission::canShow($plan);

        return ResponseService::response([
            'success' => true,
            'data' => $plan,
            'resource' => PlanResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreatePlanRequest $request)
    {
        PlanPermission::canCreate();

        $plan = $this->planService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $plan,
            'message' => 'messages.plan.created',
            'status' => 201,
            'resource' => PlanResource::class,
        ]);
    }

    public function update(UpdatePlanRequest $request, int $id)
    {
        PlanPermission::canUpdate();

        $plan = $this->planService->show($id);

        $plan = $this->planService->update($request->validated(), $plan);

        return ResponseService::response([
            'success' => true,
            'data' => $plan,
            'message' => 'messages.plan.updated',
            'status' => 200,
            'resource' => PlanResource::class,
        ]);
    }

    public function delete(int $id)
    {
        PlanPermission::canDelete();

        $plan = $this->planService->show($id);

        $this->planService->delete($plan);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.plan.deleted',
            'status' => 200,
        ]);
    }

    public function reorder(int $id, ReOrderPlanRequest $request)
    {
        PlanPermission::canReorder();

        $plan = $this->planService->show($id);

        $plan = $this->planService->reorder($plan, $request->validated());

        return $this->index(request(), 'messages.plan.reordered');
    }
}
