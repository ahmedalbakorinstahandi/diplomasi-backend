<?php

/**
 * Quick API Testing Script
 * 
 * Usage: php test_endpoints.php
 * 
 * This script tests all major endpoints to ensure they're working correctly.
 */

$baseUrl = getenv('API_BASE_URL') ?: 'http://127.0.0.1:8000/api/v1';
$token = '';
$context = 'dashboard';

// Colors for output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

function makeRequest($method, $url, $headers = [], $body = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true) ?: $response
    ];
}

function test($name, $method, $url, $headers = [], $body = null, $expectedCode = 200) {
    global $green, $red, $yellow, $reset;
    
    echo "Testing: {$name}... ";
    
    $result = makeRequest($method, $url, $headers, $body);
    
    if ($result['code'] === $expectedCode) {
        echo "{$green}✓ PASS{$reset}\n";
        return true;
    } else {
        echo "{$red}✗ FAIL{$reset} (HTTP {$result['code']}, expected {$expectedCode})\n";
        if (isset($result['body']['message'])) {
            echo "  Error: {$result['body']['message']}\n";
        }
        return false;
    }
}

echo "=== Diplomasi API Testing ===\n\n";

// Step 1: Login
echo "Step 1: Authentication\n";
$loginResult = makeRequest('POST', "{$baseUrl}/auth/login", [
    'Content-Type: application/json',
    'Accept: application/json'
], [
    'email' => 'admin@demo.test',
    'password' => 'Password123!'
]);

if ($loginResult['code'] === 200 && isset($loginResult['body']['access_token'])) {
    $token = $loginResult['body']['access_token'];
    echo "{$green}✓ Login successful{$reset}\n";
    echo "Token: " . substr($token, 0, 20) . "...\n\n";
} else {
    echo "{$red}✗ Login failed{$reset}\n";
    echo "  HTTP Code: {$loginResult['code']}\n";
    echo "  Response: " . json_encode($loginResult['body']) . "\n";
    exit(1);
}

// Common headers
$dashboardHeaders = [
    'X-Context: dashboard',
    "Authorization: Bearer {$token}",
    'Accept: application/json',
    'Content-Type: application/json'
];

$appHeaders = [
    'X-Context: app',
    "Authorization: Bearer {$token}",
    'Accept: application/json',
    'Content-Type: application/json'
];

// Step 2: Admin Endpoints
echo "Step 2: Admin Endpoints (Dashboard Context)\n";

test('List Permissions', 'GET', "{$baseUrl}/admin/permissions", $dashboardHeaders);
test('List Roles', 'GET', "{$baseUrl}/admin/roles", $dashboardHeaders);
test('Get Role', 'GET', "{$baseUrl}/admin/roles/1", $dashboardHeaders);
test('List Users', 'GET', "{$baseUrl}/admin/users", $dashboardHeaders);
test('Get User', 'GET', "{$baseUrl}/admin/users/1", $dashboardHeaders);
test('List Courses', 'GET', "{$baseUrl}/admin/courses", $dashboardHeaders);
test('Get Course', 'GET', "{$baseUrl}/admin/courses/1", $dashboardHeaders);
test('List Lessons', 'GET', "{$baseUrl}/admin/lessons", $dashboardHeaders);
test('List Levels', 'GET', "{$baseUrl}/admin/levels", $dashboardHeaders);
test('List Scenarios', 'GET', "{$baseUrl}/admin/scenarios", $dashboardHeaders);
test('List Articles', 'GET', "{$baseUrl}/admin/articles", $dashboardHeaders);
test('List Subscriptions', 'GET', "{$baseUrl}/admin/subscriptions", $dashboardHeaders);
test('List Notifications', 'GET', "{$baseUrl}/admin/notifications", $dashboardHeaders);
test('List Settings', 'GET', "{$baseUrl}/admin/settings", $dashboardHeaders);
test('Get Admin Profile', 'GET', "{$baseUrl}/admin/me", $dashboardHeaders);

echo "\n";

// Step 3: User Endpoints (App Context)
echo "Step 3: User Endpoints (App Context)\n";

test('List Courses (Public)', 'GET', "{$baseUrl}/user/courses", [
    'X-Context: app',
    'Accept: application/json'
]);
test('Get Course (Public)', 'GET', "{$baseUrl}/user/courses/1", [
    'X-Context: app',
    'Accept: application/json'
]);
test('List Articles (Public)', 'GET', "{$baseUrl}/user/articles", [
    'X-Context: app',
    'Accept: application/json'
]);
test('Get Public Settings', 'GET', "{$baseUrl}/user/settings/public", [
    'X-Context: app',
    'Accept: application/json'
]);
test('Get User Profile', 'GET', "{$baseUrl}/user/me", $appHeaders);
test('List User Notifications', 'GET', "{$baseUrl}/user/notifications", $appHeaders);
test('Get Unread Count', 'GET', "{$baseUrl}/user/notifications/unread-count", $appHeaders);

echo "\n";

// Step 4: Test Create Operations
echo "Step 4: Create Operations\n";

test('Create Role', 'POST', "{$baseUrl}/admin/roles", $dashboardHeaders, [
    'name' => 'test_role_' . time(),
    'description' => 'Test Role',
    'is_default' => false
], 201);

echo "\n";

// Step 5: Test Permissions Sync
echo "Step 5: Role Permissions Sync\n";

$roleId = 1; // Assuming role ID 1 exists
$syncResult = makeRequest('PUT', "{$baseUrl}/admin/roles/{$roleId}/permissions", $dashboardHeaders, [
    'permission_names' => [
        'article.view',
        'article.create'
    ]
]);

if ($syncResult['code'] === 200) {
    echo "{$green}✓ Role permissions synced successfully{$reset}\n";
} else {
    echo "{$red}✗ Role permissions sync failed{$reset} (HTTP {$syncResult['code']})\n";
}

echo "\n";

// Step 6: Context Testing
echo "Step 6: Context Validation\n";

// Try dashboard endpoint with app context (should fail)
$wrongContext = makeRequest('GET', "{$baseUrl}/admin/roles", [
    'X-Context: app',
    "Authorization: Bearer {$token}",
    'Accept: application/json'
]);

if ($wrongContext['code'] === 403) {
    echo "{$green}✓ Context validation working (app context blocked from dashboard){$reset}\n";
} else {
    echo "{$yellow}⚠ Context validation may not be working correctly{$reset}\n";
}

echo "\n=== Testing Complete ===\n";

