<?php

/**
 * Test Script for Subscription Endpoints (Geidea Integration)
 * 
 * Usage: php test_subscription_endpoints.php
 * 
 * Make sure to set these environment variables or update the constants below:
 * - API_BASE_URL
 * - AUTH_TOKEN
 */

// Configuration
// Use domain from Flutter logs
define('API_BASE_URL', 'https://diplomasi-backend.ahmed-albakor.com/api/v1');
define('AUTH_TOKEN', '34|DP6LQqCumICQupFZZJI7id3hyVNbD8ra4tpWYGHP4a8a2925'); // From Flutter logs
define('LOGIN_EMAIL', 'user01@demo.test'); // For login attempt
define('LOGIN_PASSWORD', 'Password123!'); // For login attempt
define('PLAN_ID', 1); // Test plan ID

$GLOBALS['auth_token'] = AUTH_TOKEN; // Use token from Flutter logs
$GLOBALS['working_base_url'] = API_BASE_URL;

// Colors for output
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");

function printHeader($text) {
    echo "\n" . BLUE . "═══════════════════════════════════════════════════════════\n";
    echo "  $text\n";
    echo "═══════════════════════════════════════════════════════════\n" . RESET;
}

function printSuccess($text) {
    echo GREEN . "✓ $text\n" . RESET;
}

function printError($text) {
    echo RED . "✗ $text\n" . RESET;
}

function printInfo($text) {
    echo YELLOW . "ℹ $text\n" . RESET;
}

function makeRequest($method, $endpoint, $data = null, $queryParams = [], $useAuth = true) {
    $baseUrl = isset($GLOBALS['current_base_url']) ? $GLOBALS['current_base_url'] : API_BASE_URL;
    $url = $baseUrl . $endpoint;
    
    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }
    
    // Debug: Print URL
    printInfo("Request: $method $url");
    
    $ch = curl_init($url);
    
    $headers = [
        'Accept: application/json',
        'Accept-Language: ar',
        'Content-Type: application/json',
        'X-Context: app',
    ];
    
    if ($useAuth && isset($GLOBALS['auth_token']) && $GLOBALS['auth_token']) {
        $headers[] = 'Authorization: Bearer ' . $GLOBALS['auth_token'];
    }
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false, // For testing only
        CURLOPT_SSL_VERIFYHOST => false, // For testing only
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false, // Don't follow redirects automatically
        CURLOPT_MAXREDIRS => 0, // Don't follow redirects
        CURLOPT_VERBOSE => false, // Set to true for debugging
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            $jsonData = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            printInfo("Request Body: $jsonData");
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            $jsonData = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            printInfo("Request Body: $jsonData");
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $curlInfo = curl_getinfo($ch);
    curl_close($ch);
    
    printInfo("Response HTTP Code: $httpCode");
    
    if ($error) {
        printError("cURL Error: $error");
        return [
            'success' => false,
            'error' => $error,
            'http_code' => 0,
        ];
    }
    
    // Check if response is HTML (redirect page)
    if (strpos($response, '<!DOCTYPE html>') !== false || strpos($response, '<html>') !== false) {
        printError("Received HTML response (likely a redirect). Check URL and server configuration.");
        printInfo("Response preview: " . substr($response, 0, 200));
    }
    
    $decoded = json_decode($response, true);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'response' => $decoded ?: $response,
        'raw_response' => $response,
        'curl_info' => $curlInfo,
    ];
}

// Step 0: Verify token works
printHeader("Step 0: Using token from Flutter logs");
printInfo("Base URL: " . API_BASE_URL);
printInfo("Token: " . substr(AUTH_TOKEN, 0, 30) . "...");

// Test token by getting user profile
$testResult = makeRequest('GET', '/user/me');
if ($testResult['success']) {
    printSuccess("Token is valid!");
    if (isset($testResult['response']['data']['email'])) {
        printInfo("Logged in as: " . $testResult['response']['data']['email']);
    }
} else {
    printError("Token validation failed (HTTP {$testResult['http_code']})");
    printInfo("Trying to login with credentials...");
    
    // Try login as fallback
    $loginData = [
        'email' => LOGIN_EMAIL,
        'password' => LOGIN_PASSWORD,
    ];
    $loginResult = makeRequest('POST', '/auth/login', $loginData, [], false);
    
    if ($loginResult['success'] && isset($loginResult['response']['access_token'])) {
        $GLOBALS['auth_token'] = $loginResult['response']['access_token'];
        printSuccess("Login successful!");
        printInfo("New Token: " . substr($GLOBALS['auth_token'], 0, 30) . "...");
    } else {
        printError("Login also failed (HTTP {$loginResult['http_code']})");
        printInfo("Response: " . json_encode($loginResult['response'] ?? $loginResult['raw_response'], JSON_PRETTY_PRINT));
        exit(1);
    }
}

// Test 1: Get Plans
printHeader("Test 1: GET /user/plans");
$result = makeRequest('GET', '/user/plans', null, [], false); // Plans are public
if ($result['success']) {
    printSuccess("Plans retrieved successfully");
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    // Extract first plan ID if available
    if (isset($result['response']['data'][0]['id'])) {
        $testPlanId = $result['response']['data'][0]['id'];
        printInfo("Using plan ID: $testPlanId for next tests");
    }
} else {
    printError("Failed to get plans");
    echo "HTTP Code: " . $result['http_code'] . "\n";
    echo "Response: " . $result['raw_response'] . "\n";
    exit(1);
}

// Test 2: Get Current Subscription
printHeader("Test 2: GET /user/subscriptions/current");
$result = makeRequest('GET', '/user/subscriptions/current');
if ($result['success']) {
    printSuccess("Current subscription retrieved");
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    printInfo("No current subscription (this is OK if user is not subscribed)");
    echo "HTTP Code: " . $result['http_code'] . "\n";
}

// Test 3: Prepare Payment
printHeader("Test 3: POST /user/subscriptions/prepare-payment");
$prepareData = [
    'plan_id' => PLAN_ID,
    'type' => 'subscription_create',
    'context' => 'app',
];
$result = makeRequest('POST', '/user/subscriptions/prepare-payment', $prepareData);

if ($result['success']) {
    printSuccess("Payment prepared successfully");
    $responseData = $result['response']['data'] ?? [];
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    $merchantReference = $responseData['merchant_reference'] ?? null;
    $checkoutUrl = $responseData['checkout_url'] ?? null;
    $sessionId = $responseData['session_id'] ?? null;
    $orderId = $responseData['order_id'] ?? null;
    
    if ($merchantReference) {
        printSuccess("Merchant Reference: $merchantReference");
    } else {
        printError("Merchant Reference is missing!");
    }
    
    if ($checkoutUrl) {
        printSuccess("Checkout URL: $checkoutUrl");
    } else {
        printError("Checkout URL is NULL - This is the main issue!");
        printInfo("Check server logs to see Geidea API response");
    }
    
    if ($sessionId) {
        printSuccess("Session ID: $sessionId");
    } else {
        printError("Session ID is NULL");
    }
    
    if ($orderId) {
        printSuccess("Order ID: $orderId");
    } else {
        printError("Order ID is NULL");
    }
    
    // Store merchant_reference for next tests
    if ($merchantReference) {
        $GLOBALS['test_merchant_reference'] = $merchantReference;
    }
} else {
    printError("Failed to prepare payment");
    echo "HTTP Code: " . $result['http_code'] . "\n";
    echo "Response: " . $result['raw_response'] . "\n";
    exit(1);
}

// Test 4: Payment Status (if merchant_reference exists)
if (isset($GLOBALS['test_merchant_reference'])) {
    printHeader("Test 4: GET /user/subscriptions/payment-status");
    $result = makeRequest('GET', '/user/subscriptions/payment-status', null, [
        'merchant_reference' => $GLOBALS['test_merchant_reference'],
    ]);
    
    if ($result['success']) {
        printSuccess("Payment status retrieved");
        echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        
        $status = $result['response']['data']['status'] ?? 'unknown';
        printInfo("Payment Status: $status");
    } else {
        printError("Failed to get payment status");
        echo "HTTP Code: " . $result['http_code'] . "\n";
        echo "Response: " . $result['raw_response'] . "\n";
    }
} else {
    printInfo("Skipping payment-status test (no merchant_reference)");
}

// Test 5: Create Subscription (with merchant_reference)
if (isset($GLOBALS['test_merchant_reference'])) {
    printHeader("Test 5: POST /user/subscriptions (Create with merchant_reference)");
    $createData = [
        'plan_id' => PLAN_ID,
        'merchant_reference' => $GLOBALS['test_merchant_reference'],
        'auto_renew' => true,
    ];
    $result = makeRequest('POST', '/user/subscriptions', $createData);
    
    if ($result['success']) {
        printSuccess("Create subscription request successful");
        echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        
        $status = $result['response']['data']['status'] ?? 'unknown';
        printInfo("Status: $status");
        
        if (isset($result['response']['data']['subscription'])) {
            printSuccess("Subscription created/retrieved");
        } elseif ($status === 'pending') {
            printInfo("Payment is pending - this is expected if webhook hasn't processed yet");
        }
    } else {
        printError("Failed to create subscription");
        echo "HTTP Code: " . $result['http_code'] . "\n";
        echo "Response: " . $result['raw_response'] . "\n";
    }
} else {
    printInfo("Skipping create-subscription test (no merchant_reference)");
}

// Test 6: Get User Subscriptions List
printHeader("Test 6: GET /user/subscriptions");
$result = makeRequest('GET', '/user/subscriptions');
if ($result['success']) {
    printSuccess("Subscriptions list retrieved");
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    printError("Failed to get subscriptions list");
    echo "HTTP Code: " . $result['http_code'] . "\n";
    echo "Response: " . $result['raw_response'] . "\n";
}

// Summary
printHeader("Test Summary");
printInfo("All tests completed. Check the results above.");
printInfo("If checkout_url is NULL, check server logs for Geidea API response.");
printInfo("Look for logs containing: 'Geidea createPaymentSession response'");
