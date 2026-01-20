<?php

namespace App\Http\Services\Billing;

use App\Models\Billing\PaymentMethod;
use App\Models\Users\User;
use App\Services\StripeService;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;

class PaymentMethodService
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * List payment methods for user
     */
    public function index(User $user)
    {
        return PaymentMethod::where('user_id', $user->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create payment method
     */
    public function create(User $user, string $paymentMethodId)
    {
        return DB::transaction(function () use ($user, $paymentMethodId) {
            // Get or create Stripe customer
            $customerId = $user->getStripeCustomer();

            // Attach payment method to customer
            $stripePaymentMethod = $this->stripeService->attachPaymentMethodToCustomer($customerId, $paymentMethodId);

            // Get payment method details
            $paymentMethodDetails = $this->stripeService->getPaymentMethod($paymentMethodId);

            // Prepare payment method data
            $paymentMethodData = [
                'user_id' => $user->id,
                'stripe_payment_method_id' => $paymentMethodId,
                'type' => $paymentMethodDetails->type ?? 'card',
                'card_brand' => $paymentMethodDetails->card->brand ?? null,
                'card_last4' => $paymentMethodDetails->card->last4 ?? null,
                'card_exp_month' => $paymentMethodDetails->card->exp_month ?? null,
                'card_exp_year' => $paymentMethodDetails->card->exp_year ?? null,
                'is_default' => false,
                'billing_details' => $paymentMethodDetails->billing_details ?? null,
                'metadata' => $paymentMethodDetails->metadata ?? null,
            ];

            // If this is the first payment method, set it as default
            $existingCount = PaymentMethod::where('user_id', $user->id)->count();
            if ($existingCount === 0) {
                $paymentMethodData['is_default'] = true;
                $this->stripeService->setDefaultPaymentMethod($customerId, $paymentMethodId);
                $user->update(['stripe_default_payment_method_id' => $paymentMethodId]);
            }

            $paymentMethod = PaymentMethod::create($paymentMethodData);

            return $paymentMethod;
        });
    }

    /**
     * Set default payment method
     */
    public function setDefault(User $user, string $paymentMethodId)
    {
        return DB::transaction(function () use ($user, $paymentMethodId) {
            $paymentMethod = PaymentMethod::where('user_id', $user->id)
                ->where('stripe_payment_method_id', $paymentMethodId)
                ->first();

            if (!$paymentMethod) {
                MessageService::abort(404, 'Payment method not found');
            }

            // Unset previous default
            PaymentMethod::where('user_id', $user->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            // Set new default
            $paymentMethod->update(['is_default' => true]);

            // Update Stripe
            $customerId = $user->getStripeCustomer();
            $this->stripeService->setDefaultPaymentMethod($customerId, $paymentMethodId);
            $user->update(['stripe_default_payment_method_id' => $paymentMethodId]);

            return $paymentMethod;
        });
    }

    /**
     * Delete payment method
     */
    public function delete(User $user, string $paymentMethodId)
    {
        return DB::transaction(function () use ($user, $paymentMethodId) {
            $paymentMethod = PaymentMethod::where('user_id', $user->id)
                ->where('stripe_payment_method_id', $paymentMethodId)
                ->first();

            if (!$paymentMethod) {
                MessageService::abort(404, 'Payment method not found');
            }

            if ($paymentMethod->is_default) {
                MessageService::abort(400, 'Cannot delete default payment method. Please set another as default first.');
            }

            // Detach from Stripe
            try {
                $this->stripeService->detachPaymentMethod($paymentMethodId);
            } catch (\Exception $e) {
                \Log::warning('Failed to detach payment method from Stripe', [
                    'payment_method_id' => $paymentMethodId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Delete from database
            $paymentMethod->delete();

            return true;
        });
    }
}
