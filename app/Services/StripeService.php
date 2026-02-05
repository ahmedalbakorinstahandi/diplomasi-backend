<?php

namespace App\Services;

use App\Models\Users\User;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class StripeService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $secret = config('services.stripe.secret');
        
        if (!$secret) {
            throw new \RuntimeException('Stripe secret key is not configured. Please set STRIPE_SECRET in your .env file.');
        }
        
        $this->stripe = new StripeClient($secret);
    }

    /**
     * Create or get Stripe customer for user
     */
    public function createOrGetCustomer(User $user): string
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        try {
            $customer = $this->stripe->customers->create([
                'email' => $user->email,
                'name' => $user->first_name . ' ' . $user->last_name,
                'phone' => $user->phone,
                'metadata' => [
                    'user_id' => $user->id,
                    'app' => 'diplomasi',
                ],
            ]);

            $user->update(['stripe_customer_id' => $customer->id]);

            return $customer->id;
        } catch (ApiErrorException $e) {
            \Log::error('Stripe customer creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update Stripe customer
     */
    public function updateCustomer(string $customerId, array $data): void
    {
        try {
            $this->stripe->customers->update($customerId, $data);
        } catch (ApiErrorException $e) {
            \Log::error('Stripe customer update failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create payment method (attach to customer)
     */
    public function attachPaymentMethodToCustomer(string $customerId, string $paymentMethodId): object
    {
        try {
            $paymentMethod = $this->stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $customerId,
            ]);

            return $paymentMethod;
        } catch (ApiErrorException $e) {
            \Log::error('Stripe payment method attach failed', [
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Set default payment method for customer
     */
    public function setDefaultPaymentMethod(string $customerId, string $paymentMethodId): void
    {
        try {
            $this->stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);
        } catch (ApiErrorException $e) {
            \Log::error('Stripe set default payment method failed', [
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * List payment methods for customer
     */
    public function listPaymentMethods(string $customerId, string $type = 'card'): array
    {
        try {
            $paymentMethods = $this->stripe->paymentMethods->all([
                'customer' => $customerId,
                'type' => $type,
            ]);

            return $paymentMethods->data;
        } catch (ApiErrorException $e) {
            \Log::error('Stripe list payment methods failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get payment method details
     */
    public function getPaymentMethod(string $paymentMethodId): object
    {
        try {
            return $this->stripe->paymentMethods->retrieve($paymentMethodId);
        } catch (ApiErrorException $e) {
            \Log::error('Stripe get payment method failed', [
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Detach payment method from customer
     */
    public function detachPaymentMethod(string $paymentMethodId): void
    {
        try {
            $this->stripe->paymentMethods->detach($paymentMethodId);
        } catch (ApiErrorException $e) {
            \Log::error('Stripe detach payment method failed', [
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get or create Stripe Product
     */
    public function getOrCreateProduct(string $planName, string $planId): string
    {
        // Try to find existing product by metadata
        try {
            $products = $this->stripe->products->all([
                'metadata' => ['plan_id' => $planId],
                'limit' => 1,
            ]);

            if (count($products->data) > 0) {
                return $products->data[0]->id;
            }
        } catch (\Exception $e) {
            // No product found, will create new one
        }

        // Create new product
        try {
            $product = $this->stripe->products->create([
                'name' => $planName,
                'metadata' => [
                    'plan_id' => $planId,
                    'app' => 'diplomasi',
                ],
            ]);

            return $product->id;
        } catch (ApiErrorException $e) {
            \Log::error('Stripe product creation failed', [
                'plan_name' => $planName,
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get or create Stripe Price from plan ID or price ID
     */
    public function getOrCreatePrice(string $planOrPriceId, float $amount, string $currency, string $interval, ?string $planName = null): string
    {
        // If it's already a price ID (starts with price_), return it
        if (str_starts_with($planOrPriceId, 'price_')) {
            try {
                // Verify the price exists
                $this->stripe->prices->retrieve($planOrPriceId);
                return $planOrPriceId;
            } catch (\Exception $e) {
                // Price doesn't exist, will create new one
            }
        }

        // Try to find existing price by lookup_key
        try {
            $prices = $this->stripe->prices->all([
                'lookup_keys' => [$planOrPriceId],
                'limit' => 1,
            ]);

            if (count($prices->data) > 0) {
                return $prices->data[0]->id;
            }
        } catch (\Exception $e) {
            // No price found, will create new one
        }

        // Get or create product
        $productId = $this->getOrCreateProduct($planName ?? $planOrPriceId, $planOrPriceId);

        // Create new price
        $stripeInterval = match($interval) {
            'monthly' => 'month',
            'semi_annual' => 'month', // 6 months
            'annual' => 'year',
            default => 'month',
        };

        $intervalCount = match($interval) {
            'semi_annual' => 6,
            default => 1,
        };

        try {
            $price = $this->stripe->prices->create([
                'product' => $productId,
                'unit_amount' => (int)($amount * 100), // Convert to cents
                'currency' => strtolower($currency),
                'recurring' => [
                    'interval' => $stripeInterval,
                    'interval_count' => $intervalCount,
                ],
                'lookup_key' => $planOrPriceId,
                'metadata' => [
                    'plan_id' => $planOrPriceId,
                ],
            ]);

            return $price->id;
        } catch (ApiErrorException $e) {
            \Log::error('Stripe price creation failed', [
                'plan_or_price_id' => $planOrPriceId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create Stripe subscription
     */
    public function createSubscription(string $customerId, string $priceId, string $paymentMethodId, array $options = []): object
    {
        try {
            $subscriptionData = [
                'customer' => $customerId,
                'items' => [['price' => $priceId]],
                'default_payment_method' => $paymentMethodId,
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => [
                    'save_default_payment_method' => 'on_subscription',
                ],
                'expand' => ['latest_invoice.payment_intent'],
            ];

            if (isset($options['trial_end'])) {
                $subscriptionData['trial_end'] = $options['trial_end'];
            }

            if (isset($options['metadata'])) {
                $subscriptionData['metadata'] = $options['metadata'];
            }

            $subscription = $this->stripe->subscriptions->create($subscriptionData);

            return $subscription;
        } catch (ApiErrorException $e) {
            \Log::error('Stripe subscription creation failed', [
                'customer_id' => $customerId,
                'price_id' => $priceId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update Stripe subscription
     */
    public function updateSubscription(string $subscriptionId, array $options): object
    {
        try {
            $updateData = [];

            if (isset($options['price_id'])) {
                $updateData['items'] = [['price' => $options['price_id']]];
                $updateData['proration_behavior'] = $options['proration_behavior'] ?? 'create_prorations';
            }

            if (isset($options['cancel_at_period_end'])) {
                $updateData['cancel_at_period_end'] = $options['cancel_at_period_end'];
            }

            if (isset($options['payment_method'])) {
                $updateData['default_payment_method'] = $options['payment_method'];
            }

            if (isset($options['metadata'])) {
                $updateData['metadata'] = $options['metadata'];
            }

            return $this->stripe->subscriptions->update($subscriptionId, $updateData);
        } catch (ApiErrorException $e) {
            \Log::error('Stripe subscription update failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancel Stripe subscription
     */
    public function cancelSubscription(string $subscriptionId, bool $cancelAtPeriodEnd = true): object
    {
        try {
            return $this->stripe->subscriptions->update($subscriptionId, [
                'cancel_at_period_end' => $cancelAtPeriodEnd,
            ]);
        } catch (ApiErrorException $e) {
            \Log::error('Stripe subscription cancel failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Resume Stripe subscription
     */
    public function resumeSubscription(string $subscriptionId): object
    {
        try {
            return $this->stripe->subscriptions->update($subscriptionId, [
                'cancel_at_period_end' => false,
            ]);
        } catch (ApiErrorException $e) {
            \Log::error('Stripe subscription resume failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get Stripe subscription
     */
    public function getSubscription(string $subscriptionId): object
    {
        try {
            return $this->stripe->subscriptions->retrieve($subscriptionId, [
                'expand' => ['latest_invoice.payment_intent', 'customer', 'default_payment_method'],
            ]);
        } catch (ApiErrorException $e) {
            \Log::error('Stripe get subscription failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create payment intent for subscription
     */
    public function createPaymentIntent(float $amount, string $currency, string $customerId, ?string $paymentMethodId = null, array $metadata = []): object
    {
        try {
            $paymentIntentData = [
                'amount' => (int)($amount * 100), // Convert to cents
                'currency' => strtolower($currency),
                'customer' => $customerId,
                'setup_future_usage' => 'off_session',
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => $metadata,
            ];

            if ($paymentMethodId) {
                $paymentIntentData['payment_method'] = $paymentMethodId;
                $paymentIntentData['confirmation_method'] = 'automatic';
                $paymentIntentData['confirm'] = true;
            }

            return $this->stripe->paymentIntents->create($paymentIntentData);
        } catch (ApiErrorException $e) {
            \Log::error('Stripe payment intent creation failed', [
                'customer_id' => $customerId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create ephemeral key for customer
     */
    public function createEphemeralKey(string $customerId, string $stripeVersion = '2023-10-16'): object
    {
        try {
            return $this->stripe->ephemeralKeys->create(
                ['customer' => $customerId],
                ['stripe_version' => $stripeVersion]
            );
        } catch (ApiErrorException $e) {
            \Log::error('Stripe ephemeral key creation failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get payment intent
     */
    public function getPaymentIntent(string $paymentIntentId): object
    {
        try {
            return $this->stripe->paymentIntents->retrieve($paymentIntentId);
        } catch (ApiErrorException $e) {
            \Log::error('Stripe get payment intent failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): object
    {
        try {
            return \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            \Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get Stripe client instance
     */
    public function getClient(): StripeClient
    {
        return $this->stripe;
    }
}
