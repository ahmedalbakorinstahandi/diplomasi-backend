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

        if (!$publicKey || !$apiPassword || !$baseUrl) {
            throw new \RuntimeException('Geidea credentials are not configured. Please set GEIDEA_PUBLIC_KEY, GEIDEA_API_PASSWORD, and GEIDEA_BASE_URL in your .env file.');
        }

        $this->publicKey = $publicKey;
        $this->apiPassword = $apiPassword;
        $this->merchantId = config('services.geidea.merchant_id');
        $this->baseUrl = $baseUrl;
        $this->environment = config('services.geidea.environment', 'sandbox');
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
            // Use Geidea HPP Checkout Create Session API
            // Endpoint: /payment-intent/api/v2/direct/session
            $endpoint = "{$this->baseUrl}/payment-intent/api/v2/direct/session";
            
            // Prepare request data
            $amount = $data['amount'] ?? null;
            $currency = $data['currency'] ?? 'SAR';
            $merchantReferenceId = $data['merchantReferenceId'] ?? null;
            $callbackUrl = $data['callbackUrl'] ?? null;
            $returnUrl = $data['returnUrl'] ?? null;
            
            if (!$amount || !$callbackUrl) {
                throw new \InvalidArgumentException('amount and callbackUrl are required');
            }
            
            // Generate timestamp (format: Y/m/d H:i:s)
            $timestamp = now()->format('Y/m/d H:i:s');
            
            // Generate signature
            $signature = $this->generateSignature(
                $this->publicKey,
                (string)$amount,
                $currency,
                $merchantReferenceId,
                $this->apiPassword,
                $timestamp
            );
            
            // Build request payload
            $requestData = [
                'amount' => (string)$amount,
                'currency' => $currency,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'callbackUrl' => $callbackUrl,
            ];
            
            // Add optional fields
            if ($merchantReferenceId) {
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
            
            Log::info('Geidea Create Session API request', [
                'endpoint' => $endpoint,
                'request_data' => $requestData,
            ]);
            
            // Make API call
            $response = $this->httpClient()->post($endpoint, $requestData);
            
            $responseBody = $response->body();
            $responseJson = $response->json();
            
            Log::info('Geidea Create Session API response', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $responseBody,
                'json' => $responseJson,
            ]);
            
            if (!$response->successful()) {
                $errorMsg = "Geidea Create Session API failed. Status: {$response->status()}, Body: {$responseBody}";
                Log::error($errorMsg);
                throw new \RuntimeException($errorMsg);
            }
            
            // Check response code
            if (isset($responseJson['responseCode']) && $responseJson['responseCode'] !== '000') {
                $errorMsg = $responseJson['detailedResponseMessage'] ?? $responseJson['responseMessage'] ?? 'Unknown error';
                Log::error('Geidea Create Session API error', [
                    'response_code' => $responseJson['responseCode'] ?? null,
                    'error_message' => $errorMsg,
                ]);
                throw new \RuntimeException("Geidea API error: {$errorMsg}");
            }
            
            // Extract session data
            if (!isset($responseJson['session']) || !isset($responseJson['session']['id'])) {
                Log::error('Geidea Create Session response missing session.id', [
                    'response' => $responseJson,
                ]);
                throw new \RuntimeException('Geidea Create Session response missing session.id');
            }
            
            $session = $responseJson['session'];
            $sessionId = $session['id'];
            
            // Build checkout URL from session ID
            $checkoutBaseUrl = $this->getCheckoutBaseUrl();
            $checkoutUrl = "{$checkoutBaseUrl}/hpp/checkout/?{$sessionId}";
            
            Log::info('Geidea Create Session successful', [
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
            // Try different query parameter names
            $queryParams = [
                'merchantReferenceId' => $merchantReference,
                'merchant_reference_id' => $merchantReference,
                'merchantReference' => $merchantReference,
                'reference' => $merchantReference,
            ];
            
            // Try different endpoint formats
            $endpoints = [
                "{$this->baseUrl}/orders?merchantReferenceId={$merchantReference}",
                "{$this->baseUrl}/orders?merchant_reference_id={$merchantReference}",
                "{$this->baseUrl}/orders?merchantReference={$merchantReference}",
                "{$this->baseUrl}/orders?reference={$merchantReference}",
                "{$this->baseUrl}/orders",
            ];
            
            foreach ($endpoints as $endpoint) {
                try {
                    if (strpos($endpoint, '?') !== false) {
                        // URL already has query string
                        $response = $this->httpClient()->get($endpoint);
                    } else {
                        // Use query parameters
                        $response = $this->httpClient()
                            ->get($endpoint, $queryParams);
                    }
                    
                    Log::info('Geidea getOrderByMerchantReference attempt', [
                        'endpoint' => $endpoint,
                        'merchant_reference' => $merchantReference,
                        'status' => $response->status(),
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        $body = $response->body();
                        
                        Log::info('Geidea getOrderByMerchantReference response', [
                            'endpoint' => $endpoint,
                            'status' => $response->status(),
                            'body' => $body,
                            'json' => $data,
                        ]);
                        
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
