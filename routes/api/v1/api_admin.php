<?php

use App\Http\Controllers\Billing\SubscriptionController;
use App\Http\Controllers\Content\ArticleController;
use App\Http\Controllers\Learning\CourseController;
use App\Http\Controllers\Learning\LessonController;
use App\Http\Controllers\Learning\LevelController;
use App\Http\Controllers\Scenarios\ScenarioController;
use App\Http\Controllers\System\NotificationController;
use App\Http\Controllers\System\SettingController;
use App\Http\Controllers\Users\PermissionListController;
use App\Http\Controllers\Users\RoleController;
use App\Http\Controllers\Users\UserController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

// Admin routes
Route::group(['prefix' => 'admin'], function () {

    Route::group(['middleware' => ['auth:sanctum', AdminMiddleware::class]], function () {

        // Users
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{id}', [UserController::class, 'show']);
        Route::post('users', [UserController::class, 'create']);
        Route::put('users/{id}', [UserController::class, 'update']);
        Route::delete('users/{id}', [UserController::class, 'delete']);

        // User profile
        Route::get('me', [UserController::class, 'getProfile']);
        Route::put('me', [UserController::class, 'updateProfile']);

        // Courses
        Route::get('courses', [CourseController::class, 'index']);
        Route::get('courses/{id}', [CourseController::class, 'show']);
        Route::post('courses', [CourseController::class, 'create']);
        Route::put('courses/{id}', [CourseController::class, 'update']);
        Route::delete('courses/{id}', [CourseController::class, 'delete']);
        Route::put('courses/{id}/reorder', [CourseController::class, 'reorder']);

        // Lessons
        Route::get('lessons', [LessonController::class, 'index']);
        Route::get('lessons/{id}', [LessonController::class, 'show']);
        Route::post('lessons', [LessonController::class, 'create']);
        Route::put('lessons/{id}', [LessonController::class, 'update']);
        Route::delete('lessons/{id}', [LessonController::class, 'delete']);
        Route::put('lessons/{id}/reorder', [LessonController::class, 'reorder']);

        // Levels
        Route::get('levels', [LevelController::class, 'index']);
        Route::get('levels/{id}', [LevelController::class, 'show']);
        Route::post('levels', [LevelController::class, 'create']);
        Route::put('levels/{id}', [LevelController::class, 'update']);
        Route::delete('levels/{id}', [LevelController::class, 'delete']);
        Route::put('levels/{id}/reorder', [LevelController::class, 'reorder']);

        // Scenarios
        Route::get('scenarios', [ScenarioController::class, 'index']);
        Route::get('scenarios/{id}', [ScenarioController::class, 'show']);
        Route::post('scenarios', [ScenarioController::class, 'create']);
        Route::put('scenarios/{id}', [ScenarioController::class, 'update']);
        Route::delete('scenarios/{id}', [ScenarioController::class, 'delete']);
        Route::put('scenarios/{id}/reorder', [ScenarioController::class, 'reorder']);

        // Articles
        Route::get('articles', [ArticleController::class, 'index']);
        Route::get('articles/{id}', [ArticleController::class, 'show']);
        Route::post('articles', [ArticleController::class, 'create']);
        Route::put('articles/{id}', [ArticleController::class, 'update']);
        Route::delete('articles/{id}', [ArticleController::class, 'delete']);

        // Subscriptions
        Route::get('subscriptions', [SubscriptionController::class, 'index']);
        Route::get('subscriptions/{id}', [SubscriptionController::class, 'show']);
        Route::post('subscriptions', [SubscriptionController::class, 'create']);
        Route::put('subscriptions/{id}', [SubscriptionController::class, 'update']);
        Route::delete('subscriptions/{id}', [SubscriptionController::class, 'delete']);
        Route::post('subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('subscriptions/{id}/renew', [SubscriptionController::class, 'renew']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/{id}', [NotificationController::class, 'show']);
        Route::post('notifications', [NotificationController::class, 'create']);
        Route::put('notifications/{id}', [NotificationController::class, 'update']);
        Route::delete('notifications/{id}', [NotificationController::class, 'delete']);

        // Settings
        Route::get('settings', [SettingController::class, 'index']);
        Route::get('settings/{idOrKey}', [SettingController::class, 'show']);
        Route::post('settings', [SettingController::class, 'create']);
        Route::put('settings', [SettingController::class, 'updateMany']);
        Route::put('settings/{idOrKey}', [SettingController::class, 'update']);
        Route::delete('settings/{idOrKey}', [SettingController::class, 'delete']);

        // RBAC
        Route::get('permissions', [PermissionListController::class, 'index']);

        Route::get('roles', [RoleController::class, 'index']);
        Route::get('roles/{id}', [RoleController::class, 'show']);
        Route::post('roles', [RoleController::class, 'create']);
        Route::put('roles/{id}', [RoleController::class, 'update']);
        Route::delete('roles/{id}', [RoleController::class, 'delete']);
        Route::put('roles/{id}/permissions', [RoleController::class, 'syncPermissions']);
    });
});
