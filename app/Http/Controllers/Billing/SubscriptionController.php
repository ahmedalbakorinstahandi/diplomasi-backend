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
     * Prepare payment for subscription (User route)
     * Returns Geidea payment session data
     */
    public function preparePayment(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'type' => 'nullable|in:subscription_create,subscription_upgrade',
            'context' => 'nullable|in:app,web',
        ]);

        $user = \App\Models\Users\User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $plan = \App\Models\Billing\Plan::find($request->plan_id);
        if (!$plan) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Plan not found',
                'status' => 404,
            ]);
        }

        $geideaService = app(\App\Services\GeideaService::class);

        // Generate merchant reference
        $merchantReference = $geideaService->generateMerchantReference();

        // Calculate expires_at (default: +30 minutes)
        $expiresAt = now()->addMinutes(30);

        // Create PaymentAttempt
        $paymentAttempt = \App\Models\Billing\PaymentAttempt::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'type' => $request->input('type', 'subscription_create'),
            'merchant_reference' => $merchantReference,
            'amount' => $plan->price,
            'currency' => 'SAR', // Geidea uses SAR
            'status' => 'initiated',
            'expires_at' => $expiresAt,
        ]);

        try {
            // Create Geidea payment session
            $sessionData = [
                'amount' => $plan->price,
                'currency' => 'SAR',
                'merchantReferenceId' => $merchantReference,
                'callbackUrl' => config('app.url') . '/api/v1/webhooks/geidea',
                'returnUrl' => config('app.url') . '/payment/return', // Optional, Flutter doesn't use it
            ];

            $geideaResponse = $geideaService->createPaymentSession($sessionData);

            // Log full response for debugging
            Log::info('Geidea response in preparePayment', [
                'merchant_reference' => $merchantReference,
                'response_type' => gettype($geideaResponse),
                'response_class' => get_class($geideaResponse),
                'response_array' => (array) $geideaResponse,
                'response_json' => json_encode($geideaResponse),
                'all_properties' => array_keys((array) $geideaResponse),
            ]);

            // Extract session data from Geidea response
            // Note: In fallback mode (Orders API), sessionId may not be available
            $sessionId = $geideaResponse->sessionId 
                ?? $geideaResponse->session_id 
                ?? $geideaResponse->id 
                ?? null;
            
            $checkoutUrl = $geideaResponse->checkoutUrl 
                ?? $geideaResponse->checkout_url 
                ?? null;
            
            // Extract order ID from session if available (may come later via webhook)
            $orderId = $geideaResponse->orderId 
                ?? $geideaResponse->order_id 
                ?? null;
            
            // Extract expiry date from session if available
            $expiresAt = null;
            if (isset($geideaResponse->session) && isset($geideaResponse->session->expiryDate)) {
                try {
                    $expiresAt = \Carbon\Carbon::parse($geideaResponse->session->expiryDate);
                } catch (\Exception $e) {
                    Log::warning('Failed to parse session expiryDate', [
                        'expiry_date' => $geideaResponse->session->expiryDate ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // checkout_url is required for Flutter to open payment page
            // If not available, we cannot proceed - user needs to wait for webhook
            if (!$checkoutUrl) {
                Log::warning('Geidea Create Session missing checkout_url - will be available via webhook', [
                    'merchant_reference' => $merchantReference,
                    'has_session_id' => !is_null($sessionId),
                    'has_checkout_url' => !is_null($checkoutUrl),
                    'note' => 'checkout_url will be updated when webhook arrives',
                ]);
                
                // Return response without checkout_url - Flutter will need to poll or wait
                // Or we can throw error to force retry
                throw new \RuntimeException('Payment session created but checkout URL not available yet. Please try again in a moment or contact support.');
            }
            
            // Log if we're in fallback mode (no sessionId)
            if (!$sessionId) {
                Log::info('Geidea Create Session in fallback mode (no sessionId)', [
                    'merchant_reference' => $merchantReference,
                    'checkout_url' => $checkoutUrl,
                    'note' => 'Using Orders API fallback - checkout_url obtained from order fetch',
                ]);
            }

            // Update PaymentAttempt with Geidea session data
            $updateData = [
                'geidea_session_id' => $sessionId,
                'checkout_url' => $checkoutUrl,
                'status' => 'pending',
            ];
            
            if ($orderId) {
                $updateData['geidea_order_id'] = $orderId;
            }
            
            $paymentAttempt->update($updateData);

            // Update expires_at if available from session
            if ($expiresAt) {
                $paymentAttempt->update([
                    'expires_at' => $expiresAt,
                ]);
            }

            // Prepare response with all required data from Geidea HPP Checkout
            $responseData = [
                'merchant_reference' => $merchantReference,
                'checkout_url' => $checkoutUrl,
                'session_id' => $sessionId,
                'amount' => $plan->price,
                'currency' => 'SAR',
                'expires_at' => $paymentAttempt->expires_at->toAtomString(),
            ];
            
            // Add optional fields if available
            if ($orderId) {
                $responseData['order_id'] = $orderId;
            }
            
            return ResponseService::response([
                'success' => true,
                'data' => $responseData,
                'status' => 200,
            ]);
        } catch (\Exception $e) {
            Log::error('Geidea prepare payment failed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'merchant_reference' => $merchantReference,
                'error' => $e->getMessage(),
            ]);

            $paymentAttempt->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);

            return ResponseService::response([
                'success' => false,
                'message' => 'Failed to prepare payment. Please try again.',
                'status' => 500,
            ]);
        }
    }

    /**
     * Create subscription with payment (User route)
     * Idempotent: returns existing subscription if already created
     */
    public function createWithPayment(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'merchant_reference' => 'required|string',
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

        // Find PaymentAttempt by merchant_reference and user_id
        $paymentAttempt = \App\Models\Billing\PaymentAttempt::byMerchantReference($request->merchant_reference)
            ->where('user_id', $user->id)
            ->first();

        if (!$paymentAttempt) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Payment attempt not found',
                'status' => 404,
            ]);
        }

        // Idempotency: if subscription already exists, return it
        if ($paymentAttempt->subscription_id) {
            $subscription = $this->subscriptionService->show($paymentAttempt->subscription_id);
            
            return ResponseService::response([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                    'subscription' => $subscription,
                    'message' => 'Subscription already created',
                ],
                'status' => 200,
                'resource' => SubscriptionResource::class,
            ]);
        }

        // Check PaymentAttempt status
        if ($paymentAttempt->status === 'completed') {
            // Create subscription (idempotent)
            $subscription = $this->subscriptionService->createFromPaymentAttempt($paymentAttempt, [
                'auto_renew' => $request->input('auto_renew', true),
            ]);

            return ResponseService::response([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                    'subscription' => $subscription,
                    'message' => 'Subscription created successfully',
                ],
                'status' => 201,
                'resource' => SubscriptionResource::class,
            ]);
        } elseif ($paymentAttempt->status === 'pending' || $paymentAttempt->status === 'verifying') {
            return ResponseService::response([
                'success' => true,
                'data' => [
                    'status' => 'pending',
                    'message' => 'Payment is still being processed. Please check payment status.',
                ],
                'status' => 200,
            ]);
        } elseif ($paymentAttempt->status === 'failed') {
            return ResponseService::response([
                'success' => false,
                'data' => [
                    'status' => 'failed',
                    'reason' => $paymentAttempt->failure_reason,
                    'message' => 'Payment failed: ' . ($paymentAttempt->failure_reason ?? 'Unknown error'),
                ],
                'status' => 400,
            ]);
        } else {
            return ResponseService::response([
                'success' => false,
                'data' => [
                    'status' => $paymentAttempt->status,
                    'message' => 'Payment is in ' . $paymentAttempt->status . ' status',
                ],
                'status' => 400,
            ]);
        }
    }

    /**
     * Get payment status (User route)
     * Self-healing: performs single API verification if attempt is old and still pending
     */
    public function getPaymentStatus(Request $request)
    {
        $request->validate([
            'merchant_reference' => 'required|string',
        ]);

        $user = \App\Models\Users\User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        // Find PaymentAttempt
        $paymentAttempt = \App\Models\Billing\PaymentAttempt::byMerchantReference($request->merchant_reference)
            ->where('user_id', $user->id)
            ->first();

        if (!$paymentAttempt) {
            return ResponseService::response([
                'success' => true,
                'data' => [
                    'status' => 'not_found',
                ],
                'status' => 200,
            ]);
        }

        // Self-healing: if status is pending/verifying and attempt is old (>60s), verify once via API
        if ($paymentAttempt->canBeVerified(60)) {
            try {
                $geideaService = app(\App\Services\GeideaService::class);
                
                // Perform single API verification
                $paymentStatus = $geideaService->getPaymentStatus($paymentAttempt->merchant_reference);
                
                if ($paymentStatus) {
                    $normalizedStatus = $paymentStatus['status'];
                    $verifiedOrder = $paymentStatus['order'];
                    
                    // Update PaymentAttempt based on verified status
                    // Also extract checkout_url from verified order if available
                    $checkoutUrl = $verifiedOrder->checkoutUrl 
                        ?? $verifiedOrder->checkout_url 
                        ?? $verifiedOrder->url 
                        ?? $verifiedOrder->redirectUrl 
                        ?? $verifiedOrder->redirect_url 
                        ?? $verifiedOrder->paymentUrl 
                        ?? $verifiedOrder->payment_url 
                        ?? null;
                    
                    if ($normalizedStatus === 'completed' && $paymentAttempt->status !== 'completed') {
                        $paymentAttempt->markCompleted();
                        $paymentAttempt->markVerified();
                        $updateData = [
                            'geidea_order_id' => $verifiedOrder->orderId ?? $verifiedOrder->id ?? $paymentAttempt->geidea_order_id,
                        ];
                        
                        // Update checkout_url if available from verified order
                        if ($checkoutUrl && !$paymentAttempt->checkout_url) {
                            $updateData['checkout_url'] = $checkoutUrl;
                        }
                        
                        $paymentAttempt->update($updateData);
                        
                        // Create subscription if not exists
                        if (!$paymentAttempt->subscription_id) {
                            try {
                                $subscription = $this->subscriptionService->createFromPaymentAttempt($paymentAttempt);
                                $paymentAttempt->update(['subscription_id' => $subscription->id]);
                            } catch (\Exception $e) {
                                Log::error('Failed to create subscription from self-healing verification', [
                                    'payment_attempt_id' => $paymentAttempt->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    } elseif ($normalizedStatus === 'failed' && $paymentAttempt->status !== 'failed') {
                        $updateData = [
                            'verified_at' => now(),
                        ];
                        
                        // Update checkout_url if available
                        if ($checkoutUrl && !$paymentAttempt->checkout_url) {
                            $updateData['checkout_url'] = $checkoutUrl;
                        }
                        
                        $paymentAttempt->markFailed($verifiedOrder->message ?? 'Payment failed');
                        $paymentAttempt->markVerified();
                        $paymentAttempt->update($updateData);
                    } elseif ($normalizedStatus === 'canceled' && $paymentAttempt->status !== 'canceled') {
                        $updateData = [
                            'status' => 'canceled',
                            'verified_at' => now(),
                        ];
                        
                        // Update checkout_url if available
                        if ($checkoutUrl && !$paymentAttempt->checkout_url) {
                            $updateData['checkout_url'] = $checkoutUrl;
                        }
                        
                        $paymentAttempt->update($updateData);
                    } else {
                        // For pending status, still try to update checkout_url if available
                        if ($checkoutUrl && !$paymentAttempt->checkout_url) {
                            $paymentAttempt->update([
                                'checkout_url' => $checkoutUrl,
                                'geidea_order_id' => $verifiedOrder->orderId ?? $verifiedOrder->id ?? $paymentAttempt->geidea_order_id,
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Self-healing verification failed', [
                    'payment_attempt_id' => $paymentAttempt->id,
                    'merchant_reference' => $paymentAttempt->merchant_reference,
                    'error' => $e->getMessage(),
                ]);
                // Continue with current status from DB
            }
            
            // Refresh attempt from database
            $paymentAttempt->refresh();
        }

        // Return current status
        $responseData = [
            'status' => $paymentAttempt->status,
            'updated_at' => $paymentAttempt->updated_at->toAtomString(),
        ];

        // Add checkout_url if available (may come from webhook or API verification)
        if ($paymentAttempt->checkout_url) {
            $responseData['checkout_url'] = $paymentAttempt->checkout_url;
        }
        
        // Add session_id and order_id if available
        if ($paymentAttempt->geidea_session_id) {
            $responseData['session_id'] = $paymentAttempt->geidea_session_id;
        }
        
        if ($paymentAttempt->geidea_order_id) {
            $responseData['order_id'] = $paymentAttempt->geidea_order_id;
        }

        if ($paymentAttempt->status === 'failed' && $paymentAttempt->failure_reason) {
            $responseData['reason'] = $paymentAttempt->failure_reason;
        }

        if ($paymentAttempt->status === 'completed' && $paymentAttempt->subscription_id) {
            $responseData['subscription_id'] = $paymentAttempt->subscription_id;
        }

        return ResponseService::response([
            'success' => true,
            'data' => $responseData,
            'status' => 200,
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

