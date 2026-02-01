<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeideaService
{
    protected string $publicKey;
    protected string $apiPassword;
    protected ?string $merchantId;
    protected string $baseUrl;
    protected string $environment;

    public function __construct()
    {
        $publicKey = config('services.geidea.public_key');
        $apiPassword = config('services.geidea.api_password');
        $baseUrl = config('services.geidea.base_url');
        $environment = config('services.geidea.environment', 'sandbox');

        if (!$publicKey || !$apiPassword) {
            throw new \RuntimeException('Geidea credentials are not configured. Please set GEIDEA_PUBLIC_KEY and GEIDEA_API_PASSWORD in your .env file.');
        }

        $this->publicKey = $publicKey;
        $this->apiPassword = $apiPassword;
        $this->merchantId = config('services.geidea.merchant_id');
        $this->environment = $environment;
        
        // Determine base URL based on environment if not explicitly set
        // According to Geidea API Reference docs:
        // KSA: https://api.ksamerchant.geidea.net
        // Egypt: https://api.merchant.geidea.net
        // UAE: https://api.geidea.ae
        if ($baseUrl) {
            $this->baseUrl = $baseUrl;
            
            // Use base URL as configured - don't auto-fix
            // Some Geidea accounts use https://api.geidea.net which is valid
            // Credentials are tied to the configured base URL
            Log::info('Geidea base URL configured', [
                'base_url' => $baseUrl,
                'environment' => $environment,
                'note' => 'Using configured base URL - ensure credentials match this URL',
            ]);
        } else {
            // Auto-detect base URL based on environment
            $this->baseUrl = match(strtolower($environment)) {
                'ksa', 'saudi' => 'https://api.ksamerchant.geidea.net',
                'uae', 'emirates' => 'https://api.geidea.ae',
                default => 'https://api.merchant.geidea.net', // Egypt (default)
            };
            
            Log::info('Geidea base URL auto-detected', [
                'environment' => $environment,
                'base_url' => $this->baseUrl,
            ]);
        }
    }

    /**
     * Get HTTP client with Basic Auth configured.
     */
    protected function httpClient()
    {
        return Http::withBasicAuth($this->publicKey, $this->apiPassword)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]);
    }

    /**
     * Generate a unique merchant reference.
     */
    public function generateMerchantReference(): string
    {
        // Format: app_prefix_timestamp_uuid
        $prefix = 'diplomasi';
        $timestamp = now()->format('YmdHis');
        $uuid = Str::uuid()->toString();
        
        return "{$prefix}_{$timestamp}_{$uuid}";
    }

    /**
     * Generate signature for Geidea API requests.
     * 
     * @param string $merchantPublicKey
     * @param string $orderAmount
     * @param string $orderCurrency
     * @param string|null $orderMerchantReferenceId
     * @param string $apiPassword
     * @param string $timestamp
     * @return string Base64 encoded signature
     */
    protected function generateSignature(
        string $merchantPublicKey,
        string $orderAmount,
        string $orderCurrency,
        ?string $orderMerchantReferenceId,
        string $apiPassword,
        string $timestamp
    ): string {
        // Format amount to 2 decimals
        $amountStr = number_format((float)$orderAmount, 2, '.', '');
        
        // Use empty string if merchantReferenceId is null
        $merchantRef = $orderMerchantReferenceId ?? '';
        
        // Concatenate: {MerchantPublicKey}{OrderAmount}{OrderCurrency}{MerchantReferenceId}{timeStamp}
        $data = "{$merchantPublicKey}{$amountStr}{$orderCurrency}{$merchantRef}{$timestamp}";
        
        // Hash (SHA-256) with API password
        $hash = hash_hmac('sha256', $data, $apiPassword, true);
        
        // Convert to Base64
        return base64_encode($hash);
    }

    /**
     * Get checkout base URL based on environment.
     * 
     * @return string
     */
    protected function getCheckoutBaseUrl(): string
    {
        // Determine checkout URL based on base URL environment
        $baseUrl = strtolower($this->baseUrl);
        
        if (strpos($baseUrl, 'ksamerchant') !== false) {
            // KSA Environment
            return 'https://www.ksamerchant.geidea.net';
        } elseif (strpos($baseUrl, 'geidea.ae') !== false) {
            // UAE Environment
            return 'https://payments.geidea.ae';
        } else {
            // Egypt Environment (default)
            return 'https://www.merchant.geidea.net';
        }
    }

    /**
     * Create payment session using Geidea HPP Checkout API.
     * According to Geidea documentation: https://docs.geidea.net/docs/geidea-checkout-v2
     *
     * @param array $data Must include: amount, currency, callbackUrl, merchantReferenceId (optional)
     * @return object Response with session.id and other session data
     */
    public function createPaymentSession(array $data): object
    {
        try {
            // Try Geidea HPP Checkout Create Session API first
            // Endpoint: /payment-intent/api/v2/direct/session
            // If this fails (502), fallback to /orders endpoint
            $endpoints = [
                "{$this->baseUrl}/payment-intent/api/v2/direct/session",
                "{$this->baseUrl}/orders", // Fallback to orders endpoint
            ];
            
            $lastError = null;
            
            foreach ($endpoints as $endpoint) {
                try {
                    $isHppCheckout = strpos($endpoint, '/payment-intent/api/v2/direct/session') !== false;
                    
                    Log::info('Geidea createPaymentSession attempt', [
                        'endpoint' => $endpoint,
                        'method' => $isHppCheckout ? 'HPP Checkout' : 'Orders API',
                    ]);
                    
                    // Prepare request data
                    $amount = $data['amount'] ?? null;
                    $currency = $data['currency'] ?? 'SAR';
                    $merchantReferenceId = $data['merchantReferenceId'] ?? null;
                    $callbackUrl = $data['callbackUrl'] ?? null;
                    $returnUrl = $data['returnUrl'] ?? null;
                    
                    if (!$amount || !$callbackUrl) {
                        throw new \InvalidArgumentException('amount and callbackUrl are required');
                    }
                    
                    // Build request payload based on endpoint type
                    if ($isHppCheckout) {
                        // HPP Checkout API requires signature and timestamp
                        $timestamp = now()->format('Y/m/d H:i:s');
                        $signature = $this->generateSignature(
                            $this->publicKey,
                            (string)$amount,
                            $currency,
                            $merchantReferenceId,
                            $this->apiPassword,
                            $timestamp
                        );
                        
                        $requestData = [
                            'amount' => (string)$amount,
                            'currency' => $currency,
                            'timestamp' => $timestamp,
                            'signature' => $signature,
                            'callbackUrl' => $callbackUrl,
                        ];
                    } else {
                        // Orders API (simpler format)
                        $requestData = [
                            'amount' => $amount,
                            'currency' => $currency,
                            'merchantReferenceId' => $merchantReferenceId,
                            'callbackUrl' => $callbackUrl,
                        ];
                    }
                    
                    // Add optional fields
                    if ($merchantReferenceId && !isset($requestData['merchantReferenceId'])) {
                        $requestData['merchantReferenceId'] = $merchantReferenceId;
                    }
                    
                    if ($returnUrl) {
                        $requestData['returnUrl'] = $returnUrl;
                    }
                    
                    if (isset($data['language'])) {
                        $requestData['language'] = $data['language'];
                    }
                    
                    if (isset($data['cardOnFile'])) {
                        $requestData['cardOnFile'] = $data['cardOnFile'];
                    }
                    
                    if (isset($data['paymentOperation'])) {
                        $requestData['paymentOperation'] = $data['paymentOperation'];
                    }
                    
                    // Add customer data if provided
                    if (isset($data['customer'])) {
                        $requestData['customer'] = $data['customer'];
                    }
                    
                    // Add order data if provided
                    if (isset($data['order'])) {
                        $requestData['order'] = $data['order'];
                    }
                    
                    Log::info('Geidea API request', [
                        'endpoint' => $endpoint,
                        'request_data' => $requestData,
                    ]);
                    
                    // Make API call
                    $response = $this->httpClient()->post($endpoint, $requestData);
                    
                    $responseBody = $response->body();
                    $responseJson = $response->json();
                    
                    Log::info('Geidea API response', [
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'body' => $responseBody,
                        'json' => $responseJson,
                    ]);
                    
                    // Handle 502 Bad Gateway - try next endpoint
                    if ($response->status() === 502) {
                        $lastError = "Endpoint returned 502 Bad Gateway";
                        Log::warning('Geidea endpoint returned 502, trying next', [
                            'endpoint' => $endpoint,
                        ]);
                        continue;
                    }
                    
                    if (!$response->successful()) {
                        $lastError = "Status {$response->status()}: {$responseBody}";
                        Log::warning('Geidea endpoint failed, trying next', [
                            'endpoint' => $endpoint,
                            'error' => $lastError,
                        ]);
                        continue;
                    }
                    
                    // Handle HPP Checkout response
                    if ($isHppCheckout && $responseJson) {
                        // Check response code
                        if (isset($responseJson['responseCode']) && $responseJson['responseCode'] !== '000') {
                            $errorMsg = $responseJson['detailedResponseMessage'] ?? $responseJson['responseMessage'] ?? 'Unknown error';
                            Log::warning('Geidea HPP Checkout API error, trying fallback', [
                                'response_code' => $responseJson['responseCode'] ?? null,
                                'error_message' => $errorMsg,
                            ]);
                            $lastError = "HPP Checkout error: {$errorMsg}";
                            continue;
                        }
                        
                        // Extract session data
                        if (!isset($responseJson['session']) || !isset($responseJson['session']['id'])) {
                            Log::warning('Geidea HPP Checkout response missing session.id, trying fallback', [
                                'response' => $responseJson,
                            ]);
                            $lastError = "HPP Checkout response missing session.id";
                            continue;
                        }
                        
                        $session = $responseJson['session'];
                        $sessionId = $session['id'];
                        
                        // Build checkout URL from session ID
                        $checkoutBaseUrl = $this->getCheckoutBaseUrl();
                        $checkoutUrl = "{$checkoutBaseUrl}/hpp/checkout/?{$sessionId}";
                        
                        Log::info('Geidea HPP Checkout Create Session successful', [
                            'session_id' => $sessionId,
                            'checkout_url' => $checkoutUrl,
                            'merchant_reference' => $merchantReferenceId,
                        ]);
                        
                        // Return session object with checkout_url
                        return (object) [
                            'session' => (object) $session,
                            'sessionId' => $sessionId,
                            'session_id' => $sessionId,
                            'id' => $sessionId,
                            'checkoutUrl' => $checkoutUrl,
                            'checkout_url' => $checkoutUrl,
                            'merchantReferenceId' => $merchantReferenceId,
                            'amount' => $amount,
                            'currency' => $currency,
                        ];
                    }
                    
                    // Handle Orders API response (204 No Content or minimal response)
                    if ($response->status() === 204 || empty($responseJson)) {
                        // Orders API returns 204 - order created but no data
                        // Try to fetch order immediately to get session.id or checkout_url
                        Log::info('Geidea Orders API returned 204 - order created, attempting to fetch order', [
                            'merchant_reference' => $merchantReferenceId,
                        ]);
                        
                        // Wait longer for Geidea to process the order (Orders API may need more time)
                        // Try multiple times with increasing delays
                        $order = null;
                        $maxRetries = 3;
                        $delayMs = 1000000; // Start with 1 second
                        
                        for ($i = 0; $i < $maxRetries; $i++) {
                            usleep($delayMs);
                            
                            // Try to get order by merchant reference
                            $order = $this->getOrderByMerchantReference($merchantReferenceId);
                            
                            if ($order) {
                                // Extract session ID or order ID from fetched order
                                $sessionId = $order->sessionId 
                                    ?? $order->session_id 
                                    ?? $order->id 
                                    ?? $order->orderId 
                                    ?? $order->order_id 
                                    ?? null;
                                
                                $checkoutUrl = $order->checkoutUrl 
                                    ?? $order->checkout_url 
                                    ?? $order->url 
                                    ?? $order->redirectUrl 
                                    ?? $order->redirect_url 
                                    ?? $order->paymentUrl 
                                    ?? $order->payment_url 
                                    ?? null;
                                
                                // Build checkout URL from session ID if available
                                if ($sessionId && !$checkoutUrl) {
                                    $checkoutBaseUrl = $this->getCheckoutBaseUrl();
                                    $checkoutUrl = "{$checkoutBaseUrl}/hpp/checkout/?{$sessionId}";
                                }
                                
                                // If we have checkout_url, return it
                                if ($checkoutUrl) {
                                    Log::info('Geidea order fetched successfully with checkout_url', [
                                        'merchant_reference' => $merchantReferenceId,
                                        'session_id' => $sessionId,
                                        'checkout_url' => $checkoutUrl,
                                        'attempt' => $i + 1,
                                    ]);
                                    
                                    return (object) [
                                        'merchantReferenceId' => $merchantReferenceId,
                                        'sessionId' => $sessionId,
                                        'session_id' => $sessionId,
                                        'id' => $sessionId,
                                        'checkoutUrl' => $checkoutUrl,
                                        'checkout_url' => $checkoutUrl,
                                        'amount' => $amount,
                                        'currency' => $currency,
                                        'order' => $order,
                                    ];
                                }
                            }
                            
                            // Increase delay for next attempt
                            $delayMs *= 2; // Exponential backoff: 1s, 2s, 4s
                        }
                        
                        // If order fetch failed or no checkout_url after retries, return minimal response
                        // checkout_url will come from webhook
                        Log::warning('Geidea order fetch failed or no checkout_url after retries - will come from webhook', [
                            'merchant_reference' => $merchantReferenceId,
                            'order_fetched' => $order !== null,
                            'retries' => $maxRetries,
                        ]);
                        
                        // Return minimal response - checkout_url will be updated via webhook
                        return (object) [
                            'merchantReferenceId' => $merchantReferenceId,
                            'status' => 'created',
                            'amount' => $amount,
                            'currency' => $currency,
                            'note' => 'Order created but checkout_url not available yet - will be updated via webhook',
                        ];
                    }
                    
                    // If we get here, response was successful but unexpected format
                    $lastError = "Unexpected response format";
                    continue;
                    
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    Log::warning('Geidea endpoint exception, trying next', [
                        'endpoint' => $endpoint,
                        'error' => $lastError,
                    ]);
                    continue;
                }
            }
            
            // All endpoints failed
            Log::error('Geidea payment session creation failed on all endpoints', [
                'endpoints_tried' => $endpoints,
                'last_error' => $lastError,
                'data' => $data,
            ]);
            
            throw new \RuntimeException('Failed to create Geidea payment session. Tried multiple endpoints. Last error: ' . $lastError);
            
        } catch (\Exception $e) {
            Log::error('Geidea Create Session exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Get order by ID from Geidea API.
     *
     * @param string $orderId
     * @return object|null
     */
    public function getOrderById(string $orderId): ?object
    {
        try {
            $response = $this->httpClient()
                ->get("{$this->baseUrl}/orders/{$orderId}");

            if (!$response->successful()) {
                if ($response->status() === 404) {
                    return null;
                }

                Log::error('Geidea get order by ID failed', [
                    'order_id' => $orderId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return (object) $response->json();
        } catch (\Exception $e) {
            Log::error('Geidea get order by ID exception', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get order by merchant reference from Geidea API.
     *
     * @param string $merchantReference
     * @return object|null
     */
    public function getOrderByMerchantReference(string $merchantReference): ?object
    {
        try {
            // According to Geidea API Reference:
            // Endpoint: GET /orders?merchantReferenceId={merchantReferenceId}
            // Try different endpoint formats
            $endpoints = [
                // Standard format from Geidea docs
                "{$this->baseUrl}/orders?merchantReferenceId=" . urlencode($merchantReference),
                // Alternative formats
                "{$this->baseUrl}/orders?merchant_reference_id=" . urlencode($merchantReference),
                "{$this->baseUrl}/orders?merchantReference=" . urlencode($merchantReference),
                "{$this->baseUrl}/orders?reference=" . urlencode($merchantReference),
                // Try with query parameters object
                "{$this->baseUrl}/orders",
            ];
            
            $queryParams = [
                'merchantReferenceId' => $merchantReference,
            ];
            
            foreach ($endpoints as $endpoint) {
                try {
                    if (strpos($endpoint, '?') !== false && strpos($endpoint, '=') !== false) {
                        // URL already has query string
                        $response = $this->httpClient()->get($endpoint);
                    } else {
                        // Use query parameters object
                        $response = $this->httpClient()
                            ->get($endpoint, $queryParams);
                    }
                    
                    Log::info('Geidea getOrderByMerchantReference attempt', [
                        'endpoint' => $endpoint,
                        'merchant_reference' => $merchantReference,
                        'status' => $response->status(),
                    ]);

                    // Handle 204 No Content (order exists but no data returned yet)
                    if ($response->status() === 204) {
                        Log::info('Geidea getOrderByMerchantReference returned 204', [
                            'endpoint' => $endpoint,
                            'merchant_reference' => $merchantReference,
                            'note' => 'Order exists but details not available yet - may need to wait for webhook',
                        ]);
                        continue; // Try next endpoint
                    }

                    if ($response->successful()) {
                        $data = $response->json();
                        $body = $response->body();
                        
                        Log::info('Geidea getOrderByMerchantReference response', [
                            'endpoint' => $endpoint,
                            'status' => $response->status(),
                            'body' => $body,
                            'json' => $data,
                        ]);
                        
                        // Skip if response is empty or null
                        if (empty($data) || $data === null) {
                            continue;
                        }
                        
                        // If response is an array, get first item
                        if (is_array($data) && isset($data[0])) {
                            return (object) $data[0];
                        }

                        // If response is an object with orders array
                        if (is_array($data) && isset($data['orders']) && count($data['orders']) > 0) {
                            return (object) $data['orders'][0];
                        }
                        
                        // If response is an object with data array
                        if (is_array($data) && isset($data['data']) && count($data['data']) > 0) {
                            return (object) $data['data'][0];
                        }

                        // If response is already an object
                        if (is_object($data) || (is_array($data) && !empty($data))) {
                            return (object) $data;
                        }
                    } else {
                        Log::warning('Geidea getOrderByMerchantReference failed for endpoint', [
                            'endpoint' => $endpoint,
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Geidea getOrderByMerchantReference exception for endpoint', [
                        'endpoint' => $endpoint,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            Log::error('Geidea get order by merchant reference failed on all endpoints', [
                'merchant_reference' => $merchantReference,
                'endpoints_tried' => $endpoints,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Geidea get order by merchant reference exception', [
                'merchant_reference' => $merchantReference,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Normalize Geidea payment status to our internal status.
     *
     * @param string|object $geideaStatus Geidea status string or order object
     * @return string Normalized status: 'completed', 'failed', 'pending', 'canceled'
     */
    public function normalizeStatus($geideaStatus): string
    {
        // If it's an object, extract status
        if (is_object($geideaStatus)) {
            $status = $geideaStatus->status ?? $geideaStatus->paymentStatus ?? null;
        } else {
            $status = $geideaStatus;
        }

        if (!$status) {
            return 'pending';
        }

        $status = strtolower(trim($status));

        // Map Geidea statuses to our internal statuses
        return match ($status) {
            'paid', 'success', 'successful', 'completed', 'captured' => 'completed',
            'failed', 'declined', 'rejected', 'error' => 'failed',
            'cancelled', 'canceled', 'voided' => 'canceled',
            'pending', 'processing', 'authorized', 'initiated' => 'pending',
            default => 'pending',
        };
    }

    /**
     * Get payment status by merchant reference.
     * This is a convenience method that fetches and normalizes status.
     *
     * @param string $merchantReference
     * @return array|null ['status' => string, 'order' => object|null]
     */
    public function getPaymentStatus(string $merchantReference): ?array
    {
        $order = $this->getOrderByMerchantReference($merchantReference);

        if (!$order) {
            return null;
        }

        $normalizedStatus = $this->normalizeStatus($order);

        return [
            'status' => $normalizedStatus,
            'order' => $order,
        ];
    }
}
