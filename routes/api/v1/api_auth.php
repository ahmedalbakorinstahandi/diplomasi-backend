<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Middleware\TouchUserActivityMiddleware;
use Illuminate\Support\Facades\Route;

// Authentication
Route::group(['prefix' => 'auth'], function () {
    Route::post('guest', [AuthController::class, 'guestStart'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('register-from-guest', [AuthController::class, 'registerFromGuest'])->middleware(['auth:sanctum', TouchUserActivityMiddleware::class, 'throttle:5,1']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:5,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware(['auth:sanctum', TouchUserActivityMiddleware::class]);
    Route::post('logout', [AuthController::class, 'logout'])->middleware(['auth:sanctum', TouchUserActivityMiddleware::class]);
    
    // Account deletion
    Route::post('request-account-deletion', [AuthController::class, 'requestAccountDeletion'])->middleware(['auth:sanctum', TouchUserActivityMiddleware::class, 'ensure.verified', 'throttle:3,1']);
    Route::post('confirm-account-deletion', [AuthController::class, 'confirmAccountDeletion'])->middleware(['auth:sanctum', TouchUserActivityMiddleware::class, 'ensure.verified', 'throttle:5,1']);
});
