<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Billing\SubscriptionPermission;
use App\Http\Requests\Billing\AdminCreateSubscriptionRequest;
use App\Http\Requests\Billing\UpdateSubscriptionRequest;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Http\Services\Billing\AdminSubscriptionService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    public function __construct(
        protected AdminSubscriptionService $subscriptionService
    ) {}

    public function index(Request $request)
    {
        SubscriptionPermission::canView();

        $subscriptions = $this->subscriptionService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'data' => $subscriptions,
            'meta' => true,
            'resource' => SubscriptionResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        SubscriptionPermission::canView();

        $subscription = $this->subscriptionService->show($id);

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'resource' => SubscriptionResource::class,
            'status' => 200,
        ]);
    }

    public function create(AdminCreateSubscriptionRequest $request)
    {
        SubscriptionPermission::canCreate();

        $subscription = $this->subscriptionService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'messages.subscription.created',
            'resource' => SubscriptionResource::class,
            'status' => 201,
        ]);
    }

    public function update(UpdateSubscriptionRequest $request, int $id)
    {
        SubscriptionPermission::canUpdate();

        $subscription = $this->subscriptionService->show($id);
        $subscription = $this->subscriptionService->update($request->validated(), $subscription);

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'messages.subscription.updated',
            'resource' => SubscriptionResource::class,
            'status' => 200,
        ]);
    }

    public function delete(int $id)
    {
        SubscriptionPermission::canDelete();

        $subscription = $this->subscriptionService->show($id);
        $this->subscriptionService->delete($subscription);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.subscription.deleted',
            'status' => 200,
        ]);
    }

    public function cancel(int $id)
    {
        SubscriptionPermission::canCancel();

        $subscription = $this->subscriptionService->show($id);
        $subscription = $this->subscriptionService->cancel($subscription);

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'messages.subscription.cancelled',
            'resource' => SubscriptionResource::class,
            'status' => 200,
        ]);
    }

    public function renew(int $id)
    {
        SubscriptionPermission::canRenew();

        $subscription = $this->subscriptionService->show($id);
        $subscription = $this->subscriptionService->renew($subscription);

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'messages.subscription.renewed',
            'resource' => SubscriptionResource::class,
            'status' => 200,
        ]);
    }
}
