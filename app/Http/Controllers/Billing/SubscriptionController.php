<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Billing\SubscriptionPermission;
use App\Http\Requests\Billing\CancelSubscriptionRequest;
use App\Http\Requests\Billing\CreateSubscriptionRequest;
use App\Http\Requests\Billing\UpgradeSubscriptionRequest;
use App\Http\Requests\Billing\UpdateSubscriptionRequest;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Http\Services\Billing\SubscriptionService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

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

    public function create(CreateSubscriptionRequest $request)
    {
        SubscriptionPermission::canCreate();

        $subscription = $this->subscriptionService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'messages.subscription.created',
            'status' => 201,
            'resource' => SubscriptionResource::class,
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
            'status' => 200,
            'resource' => SubscriptionResource::class,
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

    public function cancel(int $id, CancelSubscriptionRequest $request)
    {
        SubscriptionPermission::canCancel();

        $subscription = $this->subscriptionService->show($id);

        $subscription = $this->subscriptionService->cancel($subscription, $request->validated()['reason'] ?? null);

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'messages.subscription.cancelled',
            'status' => 200,
            'resource' => SubscriptionResource::class,
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
            'status' => 200,
            'resource' => SubscriptionResource::class,
        ]);
    }

    // upgrade subscription
    public function upgrade(int $id, UpgradeSubscriptionRequest $request)
    {
        SubscriptionPermission::canUpgrade();

        $subscription = $this->subscriptionService->show($id);

        $subscription = $this->subscriptionService->upgradeSubscription(
            $subscription,
            $request->validated()['plan_id']
        );

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'messages.subscription.upgraded',
            'status' => 200,
            'resource' => SubscriptionResource::class,
        ]);
    }
}

