<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\GovernorateController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\ListingReviewController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SliderController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'user'], function () {

    // Get Home Page
    Route::get('home', [HomeController::class, 'getHomePage']);

    // Public listings
    Route::get('listings', [ListingController::class, 'index']);
    Route::get('listings/{id}', [ListingController::class, 'show']);
    Route::get('listings/{id}/reviews', [ListingReviewController::class, 'index']);

    // Public categories, governorates, cities
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{id}', [CategoryController::class, 'show']);
    Route::get('governorates', [GovernorateController::class, 'index']);
    Route::get('governorates/{id}', [GovernorateController::class, 'show']);
    Route::get('cities', [CityController::class, 'index']);
    Route::get('cities/{id}', [CityController::class, 'show']);

    // Public properties and features
    Route::get('properties', [PropertyController::class, 'index']);
    Route::get('features', [FeatureController::class, 'index']);

    // Public sliders
    Route::get('sliders/active', [SliderController::class, 'active']);
    Route::post('sliders/{id}/click', [SliderController::class, 'click']);

    // Public settings
    Route::get('settings/public', [SettingController::class, 'publicSettings']);

    Route::post('listings/view', [ListingController::class, 'viewListing']);



    // sanctum routes
    Route::group(['middleware' => 'auth:sanctum'], function () {
        // User listings management
        Route::post('listings', [ListingController::class, 'create']);
        Route::put('listings/{id}', [ListingController::class, 'update']);
        Route::delete('listings/{id}', [ListingController::class, 'delete']);
        Route::post('listings/{id}/toggle-favorite', [ListingController::class, 'toggleFavorite']);

        // User reviews
        Route::post('reviews', [ListingReviewController::class, 'create']);
        Route::put('reviews/{id}', [ListingReviewController::class, 'update']);
        Route::delete('reviews/{id}', [ListingReviewController::class, 'delete']);

        // User notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::get('notifications/unread-count', [NotificationController::class, 'notificationsUnreadCount']);


        // User profile
        Route::get('me', [UserController::class, 'getProfile']);
        Route::put('me', [UserController::class, 'updateProfile']);
    });
});
