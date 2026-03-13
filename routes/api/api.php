<?php

use App\Http\Controllers\Billing\AppleNotificationController;
use App\Http\Controllers\Billing\MoyasarWebhookController;
use App\Http\Middleware\SetLocaleMiddleware;
use App\Http\Middleware\RequestContextMiddleware;
use Illuminate\Support\Facades\Route;

request()->headers->set('Accept', 'application/json');

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



    // Payment Webhooks (Public)
    Route::post('billing/webhooks/moyasar', [MoyasarWebhookController::class, 'receive']);
    Route::post('ios/notifications', [AppleNotificationController::class, 'receive']);
});


//payment_paid,payment_failed,payment_voided,payment_authorized,payment_captured,payment_refunded,payment_abandoned,payment_verified