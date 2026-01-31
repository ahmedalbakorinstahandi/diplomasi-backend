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
     * Create payment session/order in Geidea.
     *
     * @param array $data
     * @return object
     */
    public function createPaymentSession(array $data): object
    {
        try {
            // Try different possible endpoints (Geidea API may vary)
            // Common endpoints: /orders, /payment-intents, /payments, /checkout
            $possibleEndpoints = [
                "{$this->baseUrl}/orders",
                "{$this->baseUrl}/payment-intents",
                "{$this->baseUrl}/payments",
                "{$this->baseUrl}/checkout",
            ];
            
            $lastError = null;
            
            foreach ($possibleEndpoints as $url) {
                try {
                    Log::info('Geidea createPaymentSession attempt', [
                        'url' => $url,
                        'data' => $data,
                    ]);

                    $response = $this->httpClient()
                        ->post($url, $data);

                    $responseBody = $response->body();
                    $responseJson = $response->json();

                    Log::info('Geidea createPaymentSession response', [
                        'url' => $url,
                        'status' => $response->status(),
                        'body' => $responseBody,
                        'json' => $responseJson,
                    ]);

                    if ($response->successful()) {
                        // Handle HTTP 204 (No Content) - Order created but no response body
                        // Check headers for Location or orderId
                        $headers = $response->headers();
                        $locationHeader = $headers['Location'] ?? $headers['location'] ?? null;
                        
                        Log::info('Geidea response headers', [
                            'url' => $url,
                            'status' => $response->status(),
                            'headers' => $headers,
                            'location' => $locationHeader,
                        ]);
                        
                        // Extract orderId from Location header if present
                        $orderIdFromHeader = null;
                        if ($locationHeader) {
                            // Location might be: /orders/12345 or https://api.geidea.net/orders/12345
                            if (preg_match('/\/orders\/([^\/\?]+)/', $locationHeader, $matches)) {
                                $orderIdFromHeader = $matches[1];
                                Log::info('Extracted orderId from Location header', [
                                    'order_id' => $orderIdFromHeader,
                                    'location' => $locationHeader,
                                ]);
                            }
                        }
                        
                        if ($response->status() === 204 || empty($responseJson)) {
                            // Geidea API returns HTTP 204 (No Content) - order is created successfully
                            // but no data in response. Order details will come via webhook.
                            Log::info('Geidea returned 204 No Content - order created successfully', [
                                'url' => $url,
                                'merchantReferenceId' => $data['merchantReferenceId'] ?? null,
                                'orderIdFromHeader' => $orderIdFromHeader,
                                'note' => 'Order details (checkout_url, order_id, etc.) will be provided via webhook',
                            ]);
                            
                            // Try to get order by ID from header if available (quick check only)
                            if ($orderIdFromHeader) {
                                $order = $this->getOrderById($orderIdFromHeader);
                                if ($order) {
                                    Log::info('Successfully fetched order by ID from header', [
                                        'order_id' => $orderIdFromHeader,
                                    ]);
                                    return $order;
                                }
                            }
                            
                            // Return minimal object - order is created, details will come via webhook
                            $result = [
                                'merchantReferenceId' => $data['merchantReferenceId'] ?? null,
                                'status' => 'created',
                            ];
                            
                            if ($orderIdFromHeader) {
                                $result['orderId'] = $orderIdFromHeader;
                            }
                            
                            return (object) $result;
                        }
                        
                        $result = (object) $responseJson;
                        
                        // Log the actual structure for debugging
                        Log::info('Geidea response structure', [
                            'url' => $url,
                            'keys' => array_keys($responseJson ?? []),
                            'has_checkoutUrl' => isset($result->checkoutUrl),
                            'has_checkout_url' => isset($result->checkout_url),
                            'has_sessionId' => isset($result->sessionId),
                            'has_session_id' => isset($result->session_id),
                            'has_orderId' => isset($result->orderId),
                            'has_order_id' => isset($result->order_id),
                            'full_response_keys' => array_keys($responseJson ?? []),
                            'full_response_sample' => json_encode(array_slice($responseJson ?? [], 0, 10)),
                        ]);
                        
                        // Log ALL fields in response for debugging
                        Log::info('Geidea FULL response for debugging', [
                            'url' => $url,
                            'response_type' => gettype($responseJson),
                            'response_is_array' => is_array($responseJson),
                            'response_is_object' => is_object($responseJson),
                            'all_keys' => is_array($responseJson) ? array_keys($responseJson) : (is_object($responseJson) ? array_keys((array)$responseJson) : []),
                            'response_preview' => substr(json_encode($responseJson), 0, 1000),
                        ]);

                        return $result;
                    } else {
                        $lastError = "Status {$response->status()}: {$responseBody}";
                        Log::warning('Geidea endpoint failed, trying next', [
                            'url' => $url,
                            'error' => $lastError,
                        ]);
                        continue;
                    }
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    Log::warning('Geidea endpoint exception, trying next', [
                        'url' => $url,
                        'error' => $lastError,
                    ]);
                    continue;
                }
            }

            // All endpoints failed
            Log::error('Geidea payment session creation failed on all endpoints', [
                'endpoints_tried' => $possibleEndpoints,
                'last_error' => $lastError,
                'data' => $data,
            ]);

            throw new \RuntimeException('Failed to create Geidea payment session. Tried multiple endpoints. Last error: ' . $lastError);
        } catch (\Exception $e) {
            Log::error('Geidea payment session creation exception', [
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
