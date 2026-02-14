<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeideaService
{
    protected string $publicKey;
    protected string $apiPassword;
    protected string $baseUrl;

    public function __construct()
    {
        $config = config('services.geidea');
        $this->publicKey = $config['public_key'] ?? '';
        $this->apiPassword = $config['api_password'] ?? '';
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.merchant.geidea.net', '/');
    }

    /**
     * Create Session signature: MerchantPublicKey + OrderAmount(2 dec) + OrderCurrency + MerchantReferenceId + timeStamp → HMAC-SHA256(apiPassword) → Base64
     */
    public function createSessionSignature(float $orderAmount, string $orderCurrency, string $merchantReferenceId, string $timestamp): string
    {
        $amountStr = number_format($orderAmount, 2, '.', '');
        $data = "{$this->publicKey}{$amountStr}{$orderCurrency}{$merchantReferenceId}{$timestamp}";
        $hash = hash_hmac('sha256', $data, $this->apiPassword, true);
        return base64_encode($hash);
    }

    /**
     * Callback signature verification: MerchantPublicKey + OrderAmount + OrderCurrency + OrderId + Status + MerchantReferenceId + timeStamp → HMAC-SHA256 → Base64
     */
    public function verifyCallbackSignature(array $payload): bool
    {
        $signature = $payload['signature'] ?? '';
        $order = $payload['order'] ?? [];
        $orderAmount = $order['amount'] ?? $order['totalAmount'] ?? 0;
        $orderCurrency = $order['currency'] ?? '';
        $orderId = $order['orderId'] ?? '';
        $status = $order['status'] ?? $payload['detailedStatus'] ?? '';
        $merchantRef = $order['merchantReferenceId'] ?? '';
        $timestamp = $payload['timestamp'] ?? $order['updatedDate'] ?? '';

        $amountStr = number_format((float) $orderAmount, 2, '.', '');
        $data = "{$this->publicKey}{$amountStr}{$orderCurrency}{$orderId}{$status}{$merchantRef}{$timestamp}";
        $expected = base64_encode(hash_hmac('sha256', $data, $this->apiPassword, true));
        return hash_equals($expected, $signature);
    }

    /**
     * Create Subscription request signature: MerchantPublicKey + amount + currency + TimeStamp → HMAC-SHA256 → Base64
     */
    public function createSubscriptionSignature(float $amount, string $currency, string $timestamp): string
    {
        $amountStr = number_format($amount, 2, '.', '');
        $data = "{$this->publicKey}{$amountStr}{$currency}{$timestamp}";
        $hash = hash_hmac('sha256', $data, $this->apiPassword, true);
        return base64_encode($hash);
    }

    /**
     * Create Subscription response verification: MerchantPublicKey + recurringPaymentAmount + subscriptionId + status → HMAC-SHA256 → Base64
     */
    public function verifyCreateSubscriptionResponse(array $response): bool
    {
        $signature = $response['subscription']['signature'] ?? $response['signature'] ?? '';
        $sub = $response['subscription'] ?? [];
        $amount = $sub['recurringPaymentAmount'] ?? 0;
        $amountStr = number_format((float) $amount, 2, '.', '');
        $subId = $sub['subscriptionId'] ?? '';
        $status = $sub['status'] ?? $response['responseCode'] ?? '';
        $data = "{$this->publicKey}{$amountStr}{$subId}{$status}";
        $expected = base64_encode(hash_hmac('sha256', $data, $this->apiPassword, true));
        return hash_equals($expected, $signature);
    }

    /**
     * Get / Cancel Subscription request signature: MerchantPublicKey + SubscriptionID → HMAC-SHA256 → Base64
     */
    public function subscriptionActionSignature(string $subscriptionId): string
    {
        $data = "{$this->publicKey}{$subscriptionId}";
        $hash = hash_hmac('sha256', $data, $this->apiPassword, true);
        return base64_encode($hash);
    }

    /**
     * Get/Cancel Subscription response: MerchantPublicKey + Amount + Response Code + Detail Response Code → Base64
     */
    public function verifySubscriptionResponseSignature(array $response, ?float $amount = null): bool
    {
        $signature = $response['signature'] ?? '';
        $amountStr = number_format((float) ($amount ?? $response['amount'] ?? 0), 2, '.', '');
        $responseCode = $response['responseCode'] ?? '';
        $detailCode = $response['detailedResponseCode'] ?? $response['detailResponseCode'] ?? '';
        $data = "{$this->publicKey}{$amountStr}{$responseCode}{$detailCode}";
        $expected = base64_encode(hash_hmac('sha256', $data, $this->apiPassword, true));
        return hash_equals($expected, $signature);
    }

    protected function timestamp(): string
    {
        return now()->format('Y/m/d H:i:s');
    }

    protected function authHeader(): string
    {
        return 'Basic ' . base64_encode("{$this->publicKey}:{$this->apiPassword}");
    }

    /**
     * Create Session (Checkout V2). Returns session array or null on failure.
     */
    public function createSession(array $params): ?array
    {
        $amount = (float) ($params['amount'] ?? 0);
        $currency = $params['currency'] ?? 'EGP';
        $merchantReferenceId = $params['merchant_reference_id'] ?? (string) \Illuminate\Support\Str::uuid();
        $timestamp = $params['timestamp'] ?? $this->timestamp();
        $callbackUrl = $params['callback_url'] ?? config('services.geidea.callback_url');
        $returnUrl = $params['return_url'] ?? config('services.geidea.return_url');
        $language = $params['language'] ?? 'en';
        $subscriptionId = $params['subscription_id'] ?? null;

        $signature = $this->createSessionSignature($amount, $currency, $merchantReferenceId, $timestamp);

        $body = [
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'timestamp' => $timestamp,
            'merchantReferenceId' => $merchantReferenceId,
            'signature' => $signature,
            'callbackUrl' => $callbackUrl,
            'returnUrl' => $returnUrl,
            'language' => $language,
        ];
        if ($subscriptionId) {
            $body['subscriptionId'] = $subscriptionId;
        }

        $url = $this->baseUrl . '/payment-intent/api/v2/direct/session';

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $this->authHeader(),
        ];
        // Log full request for debugging (no password); confirm headers are sent
        Log::channel('single')->info('Geidea Create Session REQUEST', [
            'url' => $url,
            'headers_sent' => ['Content-Type' => 'application/json', 'Authorization' => 'Basic ***'],
            'body' => $body,
            'amount_raw' => $amount,
            'amount_formatted' => $body['amount'],
        ]);

        $response = Http::withHeaders($headers)->post($url, $body);

        // Log full response (status + raw body + parsed) to see exact Geidea reply
        $responseStatus = $response->status();
        $responseBodyRaw = $response->body();
        $responseBodyJson = $response->json();
        Log::channel('single')->info('Geidea Create Session RESPONSE', [
            'status' => $responseStatus,
            'body_raw' => $responseBodyRaw,
            'body_json' => $responseBodyJson,
        ]);

        if (!$response->successful()) {
            Log::warning('Geidea Create Session failed', [
                'status' => $responseStatus,
                'body_raw' => $responseBodyRaw,
                'body_json' => $responseBodyJson,
            ]);
            return null;
        }

        $data = $responseBodyJson ?? [];
        if (($data['responseCode'] ?? '') !== '000') {
            Log::warning('Geidea Create Session error', [
                'response' => $data,
                'body_raw' => $responseBodyRaw,
            ]);
            return null;
        }

        return $data;
    }

    /**
     * Create Subscription (Subscriptions v2). Returns subscription array or null.
     */
    public function createSubscription(array $params): ?array
    {
        $amount = (float) ($params['recurring_payment_amount'] ?? 0);
        $currency = $params['currency'] ?? 'EGP';
        $cycleInterval = $params['cycle_interval'] ?? 'month';
        $cycleFrequency = (int) ($params['cycle_frequency'] ?? 1);
        $typeOfPayment = $params['type_of_payment'] ?? 'RecurringPayment';
        $timestamp = $params['timestamp'] ?? $this->timestamp();
        $customerRequest = $params['customer_request'] ?? null;
        $customerId = $params['customer_id'] ?? null;
        $description = $params['description'] ?? 'Subscription';
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        $numberOfPayments = $params['number_of_payments'] ?? null;
        $isFirstPmtPBL = $params['is_first_pmt_pbl'] ?? false;

        $signature = $this->createSubscriptionSignature($amount, $currency, $timestamp);

        $body = [
            'recurringPaymentAmount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'cycleInterval' => strtolower($cycleInterval),
            'cycleFrequency' => $cycleFrequency,
            'typeOfPayment' => $typeOfPayment,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'description' => $description,
            'isFirstPmtPBL' => $isFirstPmtPBL,
            'AmountVariability' => $params['amount_variability'] ?? 'FIXED',
        ];
        if ($customerRequest) {
            $body['customerRequest'] = $customerRequest;
        }
        if ($customerId) {
            $body['customerId'] = $customerId;
        }
        if ($startDate) {
            $body['startDate'] = $startDate;
        }
        if ($endDate) {
            $body['endDate'] = $endDate;
        }
        if ($numberOfPayments !== null) {
            $body['numberOfPayments'] = $numberOfPayments;
        }

        $url = $this->baseUrl . '/subscriptions/api/v1/direct/subscription';
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => $this->authHeader(),
        ])->post($url, $body);

        if (!$response->successful()) {
            Log::warning('Geidea Create Subscription failed', ['status' => $response->status(), 'body' => $response->json()]);
            return null;
        }

        $data = $response->json();
        if (($data['responseCode'] ?? '') !== '000') {
            Log::warning('Geidea Create Subscription error', ['response' => $data]);
            return null;
        }

        return $data;
    }

    /**
     * Get Subscription by ID.
     */
    public function getSubscription(string $subscriptionId): ?array
    {
        $signature = $this->subscriptionActionSignature($subscriptionId);
        $url = $this->baseUrl . '/subscriptions/api/v1/direct/subscription/' . $subscriptionId;
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => $this->authHeader(),
        ])->withBody(json_encode(['signature' => $signature]), 'application/json')->get($url);

        if (!$response->successful()) {
            Log::warning('Geidea Get Subscription failed', ['status' => $response->status()]);
            return null;
        }
        return $response->json();
    }

    /**
     * Cancel Subscription at Geidea.
     */
    public function cancelSubscription(string $subscriptionId): bool
    {
        $signature = $this->subscriptionActionSignature($subscriptionId);
        $url = $this->baseUrl . '/subscriptions/api/v1/direct/subscription/' . $subscriptionId . '/cancel';
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => $this->authHeader(),
        ])->post($url, ['signature' => $signature]);

        if (!$response->successful()) {
            Log::warning('Geidea Cancel Subscription failed', ['status' => $response->status(), 'body' => $response->json()]);
            return false;
        }
        $data = $response->json();
        return ($data['responseCode'] ?? '') === '000';
    }

    /**
     * Fetch Order by orderId (GET).
     */
    public function fetchOrder(string $orderId): ?array
    {
        $url = $this->baseUrl . '/pgw/api/v1/direct/order/' . $orderId;
        $response = Http::withHeaders([
            'Authorization' => $this->authHeader(),
        ])->get($url);

        if (!$response->successful()) {
            Log::warning('Geidea Fetch Order failed', ['status' => $response->status()]);
            return null;
        }
        return $response->json();
    }

    /**
     * Map plan interval to Geidea cycleInterval and cycleFrequency.
     */
    public static function planIntervalToGeidea(string $interval): array
    {
        return match (strtolower($interval)) {
            'monthly' => ['cycle_interval' => 'month', 'cycle_frequency' => 1],
            'semi_annual' => ['cycle_interval' => 'month', 'cycle_frequency' => 6],
            'annual' => ['cycle_interval' => 'year', 'cycle_frequency' => 1],
            default => ['cycle_interval' => 'month', 'cycle_frequency' => 1],
        };
    }
}
