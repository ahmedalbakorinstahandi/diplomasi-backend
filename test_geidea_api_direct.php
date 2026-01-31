<?php

/**
 * Direct Geidea API Test
 * Tests Geidea API directly to see actual response structure
 */

require __DIR__ . '/vendor/autoload.php';

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Get config
$publicKey = config('services.geidea.public_key');
$apiPassword = config('services.geidea.api_password');
$baseUrl = config('services.geidea.base_url');

if (!$publicKey || !$apiPassword || !$baseUrl) {
    die("ERROR: Geidea credentials not configured in .env\n");
}

echo "═══════════════════════════════════════════════════════════\n";
echo "  Direct Geidea API Test\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "Configuration:\n";
echo "  Base URL: $baseUrl\n";
echo "  Public Key: " . substr($publicKey, 0, 20) . "...\n";
echo "  Environment: " . config('services.geidea.environment', 'sandbox') . "\n\n";

// Test data
$merchantReference = 'test_' . time() . '_' . uniqid();
$testData = [
    'amount' => '9.99',
    'currency' => 'SAR',
    'merchantReferenceId' => $merchantReference,
    'callbackUrl' => config('app.url') . '/api/v1/webhooks/geidea',
    'returnUrl' => config('app.url') . '/payment/return',
];

echo "Test Request Data:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

// Try different endpoints
$endpoints = [
    '/orders',
    '/payment-intents',
    '/payments',
    '/checkout',
    '/api/v1/orders',
    '/api/v1/payment-intents',
];

foreach ($endpoints as $endpoint) {
    $url = rtrim($baseUrl, '/') . $endpoint;
    
    echo "───────────────────────────────────────────────────────────\n";
    echo "Testing: $url\n";
    echo "───────────────────────────────────────────────────────────\n";
    
    try {
        $response = Http::withBasicAuth($publicKey, $apiPassword)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout(30)
            ->post($url, $testData);
        
        $status = $response->status();
        $body = $response->body();
        $json = $response->json();
        
        echo "HTTP Status: $status\n";
        
        if ($status >= 200 && $status < 300) {
            echo "\033[32m✓ SUCCESS\033[0m\n\n";
            echo "Response Body:\n";
            echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            
            if (is_array($json)) {
                echo "Response Keys:\n";
                echo "  " . implode(", ", array_keys($json)) . "\n\n";
                
                // Check for common fields
                $fields = ['checkoutUrl', 'checkout_url', 'url', 'redirectUrl', 'redirect_url', 
                          'sessionId', 'session_id', 'orderId', 'order_id', 'id'];
                echo "Looking for checkout/session/order fields:\n";
                foreach ($fields as $field) {
                    if (isset($json[$field])) {
                        echo "  \033[32m✓ Found: $field = " . (is_string($json[$field]) ? substr($json[$field], 0, 50) : json_encode($json[$field])) . "\033[0m\n";
                    }
                }
                echo "\n";
            }
            
            // If we got a successful response, we found the right endpoint
            echo "\033[32m✓ This endpoint works! Use this in GeideaService.\033[0m\n";
            break;
        } else {
            echo "\033[31m✗ FAILED\033[0m\n";
            echo "Response: $body\n\n";
        }
    } catch (\Exception $e) {
        echo "\033[31m✗ EXCEPTION\033[0m\n";
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "  Test Complete\n";
echo "═══════════════════════════════════════════════════════════\n";
