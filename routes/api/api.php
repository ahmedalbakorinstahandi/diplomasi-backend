<?php

use App\Http\Middleware\SetLocaleMiddleware;
use App\Http\Middleware\RequestContextMiddleware;
use Illuminate\Support\Facades\Route;

request()->headers->set('Accept', 'application/json');

// Public webhook route (no authentication required)
Route::post('webhooks/stripe', [\App\Http\Controllers\Billing\StripeWebhookController::class, 'handle']);

// Public routes
Route::prefix('v1')->middleware([SetLocaleMiddleware::class, RequestContextMiddleware::class])->group(function () {

    // Authentication
    require __DIR__ . '/v1/api_auth.php';

    // Admin routes
    require __DIR__ . '/v1/api_admin.php';

    // User routes
    require __DIR__ . '/v1/api_user.php';

    // General routes
    require __DIR__ . '/v1/api_general.php';
});
