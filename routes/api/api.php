<?php

use App\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Support\Facades\Route;

request()->headers->set('Accept', 'application/json');

// Public routes
Route::prefix('v1')->middleware([SetLocaleMiddleware::class])->group(function () {

    // Authentication
    require __DIR__ . '/v1/api_auth.php';

    // Admin routes
    require __DIR__ . '/v1/api_admin.php';

    // User routes
    require __DIR__ . '/v1/api_user.php';

    // General routes
    require __DIR__ . '/v1/api_general.php';
});
