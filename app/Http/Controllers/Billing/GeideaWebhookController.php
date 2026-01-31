<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Services\Billing\SubscriptionService;
use App\Models\Billing\PaymentAttempt;
use App\Services\GeideaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeideaWebhookController extends Controller
{
    protected GeideaService $geideaService;
    protected SubscriptionService $subscriptionService;

    public function __construct(GeideaService $geideaService, SubscriptionService $subscriptionService)
    {
        $this->geideaService = $geideaService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Handle Geidea webhook events.
     * Core Truth: Payment truth comes ONLY from Geidea Orders API verification.
     * Webhook is only a trigger, not a trusted final status.
     */
    public function handle(Request $request)
    {
        $payload = $request->all();
        
        Log::info('Geidea webhook received', [
            'payload' => $payload,
        ]);

        try {
            // Extract orderId or merchantReferenceId from webhook payload
            $orderId = $payload['orderId'] ?? $payload['order']['id'] ?? $payload['id'] ?? null;
            $merchantReference = $payload['merchantReferenceId'] ?? $payload['merchantReference'] ?? $payload['order']['merchantReferenceId'] ?? null;

            if (!$orderId && !$merchantReference) {
                Log::warning('Geidea webhook missing orderId and merchantReferenceId', [
                    'payload' => $payload,
                ]);
                return response()->json(['error' => 'Missing orderId or merchantReferenceId'], 400);
            }

            // Always verify via Geidea API (Core Truth Principle)
            $verifiedOrder = null;
            
            if ($orderId) {
                $verifiedOrder = $this->geideaService->getOrderById($orderId);
            }
            
            // If orderId lookup failed, try merchantReference
            if (!$verifiedOrder && $merchantReference) {
                $verifiedOrder = $this->geideaService->getOrderByMerchantReference($merchantReference);
            }

            if (!$verifiedOrder) {
                Log::warning('Geidea webhook: Could not verify order via API', [
                    'order_id' => $orderId,
                    'merchant_reference' => $merchantReference,
                ]);
                return response()->json(['error' => 'Order not found in Geidea API'], 404);
            }

            // Normalize status from verified API response
            $normalizedStatus = $this->geideaService->normalizeStatus($verifiedOrder);

            // Find PaymentAttempt by merchant reference (preferred) or order ID
            $paymentAttempt = null;
            
            if ($merchantReference) {
                $paymentAttempt = PaymentAttempt::byMerchantReference($merchantReference)->first();
            }
            
            if (!$paymentAttempt && $orderId) {
                $paymentAttempt = PaymentAttempt::where('geidea_order_id', $orderId)->first();
            }

            if (!$paymentAttempt) {
                Log::warning('Geidea webhook: PaymentAttempt not found', [
                    'order_id' => $orderId,
                    'merchant_reference' => $merchantReference,
                ]);
                return response()->json(['error' => 'PaymentAttempt not found'], 404);
            }

            // Process based on verified status
            if ($normalizedStatus === 'completed') {
                $this->processPaymentCompleted($paymentAttempt, $verifiedOrder);
            } elseif ($normalizedStatus === 'failed') {
                $this->processPaymentFailed($paymentAttempt, $verifiedOrder);
            } elseif ($normalizedStatus === 'canceled') {
                $this->processPaymentCanceled($paymentAttempt, $verifiedOrder);
            } else {
                // pending or other status - just update attempt
                $paymentAttempt->update([
                    'status' => 'pending',
                    'geidea_order_id' => $verifiedOrder->orderId ?? $verifiedOrder->id ?? $paymentAttempt->geidea_order_id,
                ]);
            }

            return response()->json(['received' => true], 200);
        } catch (\Exception $e) {
            Log::error('Geidea webhook handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);
            return response()->json(['error' => 'Webhook handling failed'], 500);
        }
    }

    /**
     * Process completed payment (idempotent).
     */
    protected function processPaymentCompleted(PaymentAttempt $paymentAttempt, object $verifiedOrder): void
    {
        DB::transaction(function () use ($paymentAttempt, $verifiedOrder) {
            // Idempotency check: if already completed and verified, skip
            if ($paymentAttempt->status === 'completed' && $paymentAttempt->isVerified()) {
                Log::info('Geidea webhook: Payment already completed and verified', [
                    'merchant_reference' => $paymentAttempt->merchant_reference,
                ]);
                return;
            }

            // Update PaymentAttempt
            $paymentAttempt->markCompleted();
            $paymentAttempt->markVerified();
            $paymentAttempt->update([
                'geidea_order_id' => $verifiedOrder->orderId ?? $verifiedOrder->id ?? $paymentAttempt->geidea_order_id,
                'metadata' => array_merge($paymentAttempt->metadata ?? [], [
                    'geidea_order' => $verifiedOrder,
                    'webhook_processed_at' => now()->toAtomString(),
                ]),
            ]);

            // Create subscription if not exists (idempotent)
            if (!$paymentAttempt->subscription_id) {
                try {
                    $subscription = $this->subscriptionService->createFromPaymentAttempt($paymentAttempt);
                    $paymentAttempt->update(['subscription_id' => $subscription->id]);
                    
                    Log::info('Geidea webhook: Subscription created', [
                        'merchant_reference' => $paymentAttempt->merchant_reference,
                        'subscription_id' => $subscription->id,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Geidea webhook: Failed to create subscription', [
                        'merchant_reference' => $paymentAttempt->merchant_reference,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }
        });
    }

    /**
     * Process failed payment.
     */
    protected function processPaymentFailed(PaymentAttempt $paymentAttempt, object $verifiedOrder): void
    {
        $failureReason = $verifiedOrder->message ?? $verifiedOrder->error ?? 'Payment failed';
        
        $paymentAttempt->markFailed($failureReason);
        $paymentAttempt->markVerified();
        $paymentAttempt->update([
            'geidea_order_id' => $verifiedOrder->orderId ?? $verifiedOrder->id ?? $paymentAttempt->geidea_order_id,
            'metadata' => array_merge($paymentAttempt->metadata ?? [], [
                'geidea_order' => $verifiedOrder,
                'webhook_processed_at' => now()->toIso8601String(),
            ]),
        ]);

        Log::info('Geidea webhook: Payment marked as failed', [
            'merchant_reference' => $paymentAttempt->merchant_reference,
            'reason' => $failureReason,
        ]);
    }

    /**
     * Process canceled payment.
     */
    protected function processPaymentCanceled(PaymentAttempt $paymentAttempt, object $verifiedOrder): void
    {
        $paymentAttempt->update([
            'status' => 'canceled',
            'verified_at' => now(),
            'geidea_order_id' => $verifiedOrder->orderId ?? $verifiedOrder->id ?? $paymentAttempt->geidea_order_id,
            'metadata' => array_merge($paymentAttempt->metadata ?? [], [
                'geidea_order' => $verifiedOrder,
                'webhook_processed_at' => now()->toIso8601String(),
            ]),
        ]);

        Log::info('Geidea webhook: Payment marked as canceled', [
            'merchant_reference' => $paymentAttempt->merchant_reference,
        ]);
    }
}
