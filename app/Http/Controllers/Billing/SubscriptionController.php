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
use Illuminate\Support\Facades\Log;

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

        $data = $request->validated();
        
        // إضافة user_id من الطلب (لـ Admin) أو من المستخدم المصادق عليه
        // ============================================================
        // TODO: يمكن إضافة logic هنا لأخذ user_id من authenticated user
        // إذا كان الطلب من User routes وليس Admin routes
        // ============================================================
        // $user = \App\Models\Users\User::auth();
        // if (!$data['user_id'] && $user) {
        //     $data['user_id'] = $user->id;
        // }
        
        $subscription = $this->subscriptionService->create($data);

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

    /**
     * Create subscription (User route) – no payment gateway; subscription created locally only.
     */
    public function createForUser(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'auto_renew' => 'boolean',
        ]);

        $user = \App\Models\Users\User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $subscription = $this->subscriptionService->create([
            'user_id' => $user->id,
            'plan_id' => $request->plan_id,
            'auto_renew' => $request->input('auto_renew', true),
        ]);

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'messages.subscription.created',
            'status' => 201,
            'resource' => SubscriptionResource::class,
        ]);
    }

    /**
     * Get current subscription (User route)
     */
    public function getCurrent()
    {
        $user = \App\Models\Users\User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $subscription = $this->subscriptionService->getCurrent($user);

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'status' => 200,
            'resource' => SubscriptionResource::class,
        ]);
    }

    /**
     * Cancel auto-renewal (User route)
     */
    public function cancelAutoRenew(int $id)
    {
        $user = \App\Models\Users\User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $subscription = $this->subscriptionService->show($id);

        // Verify ownership
        if ($subscription->user_id !== $user->id) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 403,
            ]);
        }

        $subscription = $this->subscriptionService->cancelAutoRenew($subscription);

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'Auto-renewal cancelled',
            'status' => 200,
            'resource' => SubscriptionResource::class,
        ]);
    }

    /**
     * Resume auto-renewal (User route)
     */
    public function resumeAutoRenew(int $id)
    {
        $user = \App\Models\Users\User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $subscription = $this->subscriptionService->show($id);

        // Verify ownership
        if ($subscription->user_id !== $user->id) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 403,
            ]);
        }

        $subscription = $this->subscriptionService->resumeAutoRenew($subscription);

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'Auto-renewal resumed',
            'status' => 200,
            'resource' => SubscriptionResource::class,
        ]);
    }

    /**
     * Upgrade subscription (User route)
     */
    public function upgradeUser(int $id, UpgradeSubscriptionRequest $request)
    {
        $user = \App\Models\Users\User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $subscription = $this->subscriptionService->show($id);

        // Verify ownership
        if ($subscription->user_id !== $user->id) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 403,
            ]);
        }

        $subscription = $this->subscriptionService->upgradeSubscription(
            $subscription,
            $request->validated()['plan_id']
        );

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'Subscription upgraded successfully',
            'status' => 200,
            'resource' => SubscriptionResource::class,
        ]);
    }

    /**
     * Pause subscription (Admin route)
     */
    public function pause(int $id, Request $request)
    {
        SubscriptionPermission::canUpdate();

        $subscription = $this->subscriptionService->show($id);

        $subscription = $this->subscriptionService->pause($subscription, $request->input('reason'));

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'Subscription paused',
            'status' => 200,
            'resource' => SubscriptionResource::class,
        ]);
    }

    /**
     * Resume subscription (Admin route)
     */
    public function resume(int $id)
    {
        SubscriptionPermission::canUpdate();

        $subscription = $this->subscriptionService->show($id);

        $subscription = $this->subscriptionService->resume($subscription);

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'Subscription resumed',
            'status' => 200,
            'resource' => SubscriptionResource::class,
        ]);
    }

    /**
     * Manual renewal (Admin route)
     */
    public function renewManual(int $id, Request $request)
    {
        SubscriptionPermission::canRenew();

        $request->validate([
            'days' => 'nullable|integer|min:1',
        ]);

        $subscription = $this->subscriptionService->show($id);

        $subscription = $this->subscriptionService->renewManual($subscription, $request->input('days'));

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'Subscription renewed manually',
            'status' => 200,
            'resource' => SubscriptionResource::class,
        ]);
    }

    /**
     * Extend subscription (Admin route)
     */
    public function extend(int $id, Request $request)
    {
        SubscriptionPermission::canUpdate();

        $request->validate([
            'days' => 'required|integer|min:1',
        ]);

        $subscription = $this->subscriptionService->show($id);

        $subscription = $this->subscriptionService->extend($subscription, $request->input('days'));

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'message' => 'Subscription extended',
            'status' => 200,
            'resource' => SubscriptionResource::class,
        ]);
    }

    /**
     * Get user subscriptions list (User route)
     */
    public function getUserSubscriptions(Request $request)
    {
        $user = \App\Models\Users\User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $subscriptions = $this->subscriptionService->getUserSubscriptions($user, $request->all());

        return ResponseService::response([
            'success' => true,
            'data' => $subscriptions,
            'meta' => true,
            'resource' => SubscriptionResource::class,
            'status' => 200,
        ]);
    }

    /**
     * Prepare payment (Geidea): create attempt, optional subscription, session; return session_id and checkout_url.
     */
    public function preparePayment(Request $request)
    {
        $user = \App\Models\Users\User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'auto_renew' => 'boolean',
        ]);
        $result = $this->subscriptionService->preparePayment(
            (int) $request->plan_id,
            $user,
            $request->boolean('auto_renew', false)
        );
        if (!empty($result['error'])) {
            return ResponseService::response([
                'success' => false,
                'message' => $result['error'],
                'data' => $result,
                'status' => 422,
            ]);
        }
        return ResponseService::response([
            'success' => true,
            'data' => $result,
            'status' => 200,
        ]);
    }

    /**
     * Get payment status by merchant_reference (User route).
     */
    public function getPaymentStatus(string $merchantReference)
    {
        $user = \App\Models\Users\User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }
        $status = $this->subscriptionService->getPaymentStatus($merchantReference, $user);
        if ($status === null) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Payment attempt not found',
                'status' => 404,
            ]);
        }
        return ResponseService::response([
            'success' => true,
            'data' => $status,
            'status' => 200,
        ]);
    }

    /**
     * Get user subscription details (User route)
     */
    public function getUserSubscription(int $id)
    {
        $user = \App\Models\Users\User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $subscription = $this->subscriptionService->getUserSubscription($user, $id);

        return ResponseService::response([
            'success' => true,
            'data' => $subscription,
            'resource' => SubscriptionResource::class,
            'status' => 200,
        ]);
    }
}

