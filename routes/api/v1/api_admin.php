<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GovernorateController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\SliderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ListingReviewController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

// Admin routes
Route::group(['prefix' => 'admin'], function () {

    Route::group(['middleware' => 'auth:sanctum', AdminMiddleware::class], function () {

        // Users
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{id}', [UserController::class, 'show']);
        Route::post('users', [UserController::class, 'create']);
        Route::put('users/{id}', [UserController::class, 'update']);
        Route::delete('users/{id}', [UserController::class, 'delete']);

        // User profile
        Route::get('me', [UserController::class, 'getProfile']);
        Route::put('me', [UserController::class, 'updateProfile']);

        // Categories
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/{id}', [CategoryController::class, 'show']);
        Route::post('categories', [CategoryController::class, 'create']);
        Route::put('categories/{id}', [CategoryController::class, 'update']);
        Route::delete('categories/{id}', [CategoryController::class, 'delete']);
        Route::put('categories/{id}/reorder', [CategoryController::class, 'reorder']);

        // Properties
        Route::get('properties', [PropertyController::class, 'index']);
        Route::get('properties/{id}', [PropertyController::class, 'show']);
        Route::post('properties', [PropertyController::class, 'create']);
        Route::put('properties/{id}', [PropertyController::class, 'update']);
        Route::delete('properties/{id}', [PropertyController::class, 'delete']);
        Route::put('properties/{id}/reorder', [PropertyController::class, 'reorder']);

        // Features
        Route::get('features', [FeatureController::class, 'index']);
        Route::get('features/{id}', [FeatureController::class, 'show']);
        Route::post('features', [FeatureController::class, 'create']);
        Route::put('features/{id}', [FeatureController::class, 'update']);
        Route::delete('features/{id}', [FeatureController::class, 'delete']);
        Route::put('features/{id}/reorder', [FeatureController::class, 'reorder']);

        // Governorates
        Route::get('governorates', [GovernorateController::class, 'index']);
        Route::get('governorates/{id}', [GovernorateController::class, 'show']);
        Route::post('governorates', [GovernorateController::class, 'create']);
        Route::put('governorates/{id}', [GovernorateController::class, 'update']);
        Route::delete('governorates/{id}', [GovernorateController::class, 'delete']);
        Route::put('governorates/{id}/reorder', [GovernorateController::class, 'reorder']);

        // Cities
        Route::get('cities', [CityController::class, 'index']);
        Route::get('cities/{id}', [CityController::class, 'show']);
        Route::post('cities', [CityController::class, 'create']);
        Route::put('cities/{id}', [CityController::class, 'update']);
        Route::delete('cities/{id}', [CityController::class, 'delete']);
        Route::put('cities/{id}/reorder', [CityController::class, 'reorder']);

        // Sliders
        Route::get('sliders', [SliderController::class, 'index']);
        Route::get('sliders/{id}', [SliderController::class, 'show']);
        Route::post('sliders', [SliderController::class, 'create']);
        Route::put('sliders/{id}', [SliderController::class, 'update']);
        Route::delete('sliders/{id}', [SliderController::class, 'delete']);
        Route::post('sliders/{id}/click', [SliderController::class, 'click']);
        Route::put('sliders/{id}/reorder', [SliderController::class, 'reorder']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/{id}', [NotificationController::class, 'show']);
        Route::post('notifications', [NotificationController::class, 'create']);
        Route::put('notifications/{id}', [NotificationController::class, 'update']);
        Route::delete('notifications/{id}', [NotificationController::class, 'delete']);

        // Reviews
        Route::get('reviews', [ListingReviewController::class, 'index']);
        Route::get('reviews/{id}', [ListingReviewController::class, 'show']);
        Route::put('reviews/{id}', [ListingReviewController::class, 'update']);
        Route::delete('reviews/{id}', [ListingReviewController::class, 'delete']);

        // Settings
        Route::get('settings', [SettingController::class, 'index']);
        Route::get('settings/{idOrKey}', [SettingController::class, 'show']);
        Route::post('settings', [SettingController::class, 'create']);
        Route::put('settings', [SettingController::class, 'updateMany']);
        Route::put('settings/{idOrKey}', [SettingController::class, 'updateOne']);
        Route::delete('settings/{idOrKey}', [SettingController::class, 'delete']);

        // Listings
        Route::get('listings', [ListingController::class, 'index']);
        Route::get('listings/{id}', [ListingController::class, 'show']);
        Route::post('listings', [ListingController::class, 'create']);
        Route::put('listings/{id}', [ListingController::class, 'update']);
        Route::delete('listings/{id}', [ListingController::class, 'delete']);
        Route::put('listings/{id}/reorder', [ListingController::class, 'reorder']);
    });
});
