<?php

/**
 * Complete Subscription Endpoints Test
 * Tests all subscription endpoints in the correct flow
 */

define('API_BASE_URL', 'https://diplomasi-backend.ahmed-albakor.com/api/v1');
define('AUTH_TOKEN', '34|DP6LQqCumICQupFZZJI7id3hyVNbD8ra4tpWYGHP4a8a2925');
define('PLAN_ID', 1);

$GLOBALS['auth_token'] = AUTH_TOKEN;
$GLOBALS['test_merchant_reference'] = null;
$GLOBALS['test_results'] = [];

function printHeader($text) {
    echo "\n" . "\033[34m" . "═══════════════════════════════════════════════════════════\n";
    echo "  $text\n";
    echo "═══════════════════════════════════════════════════════════\n" . "\033[0m";
}

function printSuccess($text) {
    echo "\033[32m" . "✓ $text\n" . "\033[0m";
    $GLOBALS['test_results'][] = ['status' => 'success', 'message' => $text];
}

function printError($text) {
    echo "\033[31m" . "✗ $text\n" . "\033[0m";
    $GLOBALS['test_results'][] = ['status' => 'error', 'message' => $text];
}

function printInfo($text) {
    echo "\033[33m" . "ℹ $text\n" . "\033[0m";
}

function makeRequest($method, $endpoint, $data = null, $queryParams = []) {
    $url = API_BASE_URL . $endpoint;
    
    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }
    
    $ch = curl_init($url);
    
    $headers = [
        'Accept: application/json',
        'Accept-Language: ar',
        'Content-Type: application/json',
        'X-Context: app',
        'Authorization: Bearer ' . $GLOBALS['auth_token'],
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error, 'http_code' => 0];
    }
    
    $decoded = json_decode($response, true);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'response' => $decoded ?: $response,
        'raw_response' => $response,
    ];
}

// Test Flow
printHeader("Complete Subscription Endpoints Test");

// 1. Verify Authentication
printHeader("1. Authentication Check");
$result = makeRequest('GET', '/user/me');
if ($result['success']) {
    printSuccess("Authentication: Valid token");
    $email = $result['response']['data']['email'] ?? 'unknown';
    printInfo("Logged in as: $email");
} else {
    printError("Authentication: Failed (HTTP {$result['http_code']})");
    exit(1);
}

// 2. Get Plans
printHeader("2. Get Plans");
$result = makeRequest('GET', '/user/plans');
if ($result['success'] && isset($result['response']['data']) && count($result['response']['data']) > 0) {
    printSuccess("Get Plans: Success");
    $plansCount = count($result['response']['data']);
    printInfo("Found $plansCount plans");
} else {
    printError("Get Plans: Failed");
}

// 3. Get Current Subscription
printHeader("3. Get Current Subscription");
$result = makeRequest('GET', '/user/subscriptions/current');
if ($result['success']) {
    if (isset($result['response']['data']['id'])) {
        printSuccess("Get Current Subscription: Found active subscription");
        $subId = $result['response']['data']['id'];
        $planName = $result['response']['data']['plan']['name'] ?? 'Unknown';
        printInfo("Subscription ID: $subId, Plan: $planName");
    } else {
        printInfo("Get Current Subscription: No active subscription (OK)");
    }
} else {
    printError("Get Current Subscription: Failed (HTTP {$result['http_code']})");
}

// 4. Prepare Payment
printHeader("4. Prepare Payment (Geidea)");
$prepareData = [
    'plan_id' => PLAN_ID,
    'type' => 'subscription_create',
    'context' => 'app',
];
$result = makeRequest('POST', '/user/subscriptions/prepare-payment', $prepareData);

if ($result['success']) {
    $data = $result['response']['data'] ?? [];
    $merchantRef = $data['merchant_reference'] ?? null;
    $checkoutUrl = $data['checkout_url'] ?? null;
    $sessionId = $data['session_id'] ?? null;
    $orderId = $data['order_id'] ?? null;
    
    if ($merchantRef) {
        printSuccess("Prepare Payment: Success");
        printInfo("Merchant Reference: $merchantRef");
        $GLOBALS['test_merchant_reference'] = $merchantRef;
        
        // Check critical fields
        $issues = [];
        if (!$checkoutUrl) $issues[] = "checkout_url is NULL";
        if (!$sessionId) $issues[] = "session_id is NULL";
        if (!$orderId) $issues[] = "order_id is NULL";
        
        if (empty($issues)) {
            printSuccess("All Geidea fields present");
        } else {
            printError("Geidea API fields missing: " . implode(", ", $issues));
            printInfo("This may be normal if Geidea API structure differs");
            printInfo("Check server logs: storage/logs/laravel.log | grep Geidea");
        }
    } else {
        printError("Prepare Payment: Missing merchant_reference");
    }
} else {
    printError("Prepare Payment: Failed (HTTP {$result['http_code']})");
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
}

// 5. Payment Status (if merchant_reference exists)
if ($GLOBALS['test_merchant_reference']) {
    printHeader("5. Get Payment Status");
    $result = makeRequest('GET', '/user/subscriptions/payment-status', null, [
        'merchant_reference' => $GLOBALS['test_merchant_reference'],
    ]);
    
    if ($result['success']) {
        printSuccess("Get Payment Status: Success");
        $status = $result['response']['data']['status'] ?? 'unknown';
        printInfo("Payment Status: $status");
    } else {
        $httpCode = $result['http_code'];
        if ($httpCode === 500) {
            printError("Get Payment Status: Server Error (HTTP 500)");
            printInfo("This may be a route conflict issue. Check routes order.");
            printInfo("Route 'payment-status' must come before 'subscriptions/{id}'");
        } else {
            printError("Get Payment Status: Failed (HTTP $httpCode)");
        }
    }
} else {
    printInfo("Skipping Payment Status test (no merchant_reference)");
}

// 6. Create Subscription
if ($GLOBALS['test_merchant_reference']) {
    printHeader("6. Create Subscription");
    $createData = [
        'plan_id' => PLAN_ID,
        'merchant_reference' => $GLOBALS['test_merchant_reference'],
        'auto_renew' => true,
    ];
    $result = makeRequest('POST', '/user/subscriptions', $createData);
    
    if ($result['success']) {
        printSuccess("Create Subscription: Success");
        $status = $result['response']['data']['status'] ?? 'unknown';
        printInfo("Status: $status");
        
        if (isset($result['response']['data']['subscription'])) {
            printSuccess("Subscription created/retrieved");
        } elseif ($status === 'pending') {
            printInfo("Payment pending (webhook not processed yet)");
        }
    } else {
        printError("Create Subscription: Failed (HTTP {$result['http_code']})");
    }
} else {
    printInfo("Skipping Create Subscription test (no merchant_reference)");
}

// 7. Get Subscriptions List
printHeader("7. Get Subscriptions List");
$result = makeRequest('GET', '/user/subscriptions');
if ($result['success'] && isset($result['response']['data'])) {
    printSuccess("Get Subscriptions List: Success");
    $count = count($result['response']['data']);
    printInfo("Found $count subscription(s)");
} else {
    printError("Get Subscriptions List: Failed (HTTP {$result['http_code']})");
}

// Summary
printHeader("Test Summary");
$successCount = count(array_filter($GLOBALS['test_results'], fn($r) => $r['status'] === 'success'));
$errorCount = count(array_filter($GLOBALS['test_results'], fn($r) => $r['status'] === 'error'));
$total = count($GLOBALS['test_results']);

echo "\n";
echo "Total Tests: $total\n";
echo "\033[32m" . "Passed: $successCount\n" . "\033[0m";
echo "\033[31m" . "Failed: $errorCount\n" . "\033[0m";

if ($errorCount > 0) {
    echo "\n\033[31m" . "Issues Found:\n" . "\033[0m";
    foreach ($GLOBALS['test_results'] as $result) {
        if ($result['status'] === 'error') {
            echo "  - " . $result['message'] . "\n";
        }
    }
}

echo "\n";
printInfo("Next Steps:");
printInfo("1. If checkout_url is NULL: Check Geidea API documentation and server logs");
printInfo("2. If payment-status returns 500: Ensure routes are in correct order on server");
printInfo("3. Deploy updated routes file to server if route order was changed");
