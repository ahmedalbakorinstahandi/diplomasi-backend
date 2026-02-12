<?php

use App\Http\Controllers\Billing\PlanController;
use App\Http\Controllers\Billing\SubscriptionController;
use App\Http\Controllers\Content\ArticleController;
use App\Http\Controllers\Content\FaqController;
use App\Http\Controllers\Learning\CourseController;
use App\Http\Controllers\Learning\GlossaryTermController;
use App\Http\Controllers\Learning\LessonController;
use App\Http\Controllers\Learning\LevelController;
use App\Http\Controllers\Learning\LevelTrackController;
use App\Http\Controllers\Progress\ProgressController;
use App\Http\Controllers\Scenarios\ScenarioController;
use App\Http\Controllers\System\CertificateController;
use App\Http\Controllers\System\NotificationController;
use App\Http\Controllers\System\SettingController;
use App\Http\Controllers\Users\PermissionsController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'user'], function () {

    // Public courses
    Route::get('courses', [CourseController::class, 'index']);
    Route::get('courses/{id}', [CourseController::class, 'show']);

    // Public lessons videos url
    Route::get('lessons/videos-url', [LessonController::class, 'getVideosUrl']);


    // Public lessons
    Route::get('lessons', [LessonController::class, 'index']);
    Route::get('lessons/{id}', [LessonController::class, 'show']);


    // Public levels
    Route::get('levels', [LevelController::class, 'index']);
    Route::get('levels/{id}', [LevelController::class, 'show']);

    // Public level tracks
    Route::get('level-tracks', [LevelTrackController::class, 'index']);

    // Public scenarios
    Route::get('scenarios', [ScenarioController::class, 'index']);
    Route::get('scenarios/{id}', [ScenarioController::class, 'show']);

    // Public articles
    Route::get('articles', [ArticleController::class, 'index']);
    Route::get('articles/{id}', [ArticleController::class, 'show']);

    // Public glossary terms
    Route::get('glossary-terms', [GlossaryTermController::class, 'index']);
    Route::get('glossary-terms/{id}', [GlossaryTermController::class, 'show']);

    // Public FAQs
    Route::get('faqs', [FaqController::class, 'index']);
    Route::get('faqs/{id}', [FaqController::class, 'show']);

    // Public plans
    Route::get('plans', [PlanController::class, 'index']);
    Route::get('plans/{id}', [PlanController::class, 'show']);



    // Sanctum routes
    Route::group(['middleware' => 'auth:sanctum'], function () {
        // Dashboard permissions exposure (requires X-Context: dashboard + admin.access)
        Route::get('permissions', [PermissionsController::class, 'index']);

        // User profile
        Route::get('me', [UserController::class, 'getProfile']);
        Route::put('me', [UserController::class, 'updateProfile']);

        // Progress
        Route::get('progress/{type}', [ProgressController::class, 'index']);
        Route::get('progress/{type}/{id}', [ProgressController::class, 'show']);
        Route::post('progress/{type}', [ProgressController::class, 'create']);
        Route::put('progress/{type}/{id}', [ProgressController::class, 'update']);

        // Lessons - Questions and Attempts
        Route::post('lessons/{lessonId}/start-attempt', [LessonController::class, 'startAttempt']);
        Route::get('lessons/{lessonId}/questions', [LessonController::class, 'getQuestions']);
        Route::get('lessons/{lessonId}/attempts/{attemptId}/current-question', [LessonController::class, 'getCurrentQuestion']);
        Route::post('lessons/{lessonId}/attempts/{attemptId}/submit-answer', [LessonController::class, 'submitAnswer']);
        Route::post('lessons/{lessonId}/attempts/{attemptId}/finish', [LessonController::class, 'finishAttempt']);
        Route::post('lessons/{lessonId}/attempts/{attemptId}/mark-video-watched', [LessonController::class, 'markVideoWatched']);

        // Scenarios - User actions
        Route::post('scenarios/{id}/start-attempt', [ScenarioController::class, 'startAttempt']);
        Route::get('scenarios/{id}/attempts/{attemptId}/current-question', [ScenarioController::class, 'getCurrentQuestion']);
        Route::post('scenarios/submit-answer', [ScenarioController::class, 'submitAnswer']);
        Route::post('scenarios/{id}/attempts/{attemptId}/finish', [ScenarioController::class, 'finishAttempt']);
        Route::post('scenarios/{id}/attempts/{attemptId}/mark-description-read', [ScenarioController::class, 'markDescriptionRead']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::get('notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
        Route::get('notifications/{id}', [NotificationController::class, 'show']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);

        // Certificates
        Route::get('certificates', [CertificateController::class, 'index']);
        Route::get('certificates/{id}', [CertificateController::class, 'show']);
        Route::get('certificates/{id}/download', [CertificateController::class, 'download']);
        Route::get('certificates/{id}/verify-image', [CertificateController::class, 'verifyImage']);

        // Subscriptions
        // IMPORTANT: Specific routes must come before parameterized routes
        Route::get('subscriptions', [SubscriptionController::class, 'getUserSubscriptions']);
        Route::get('subscriptions/current', [SubscriptionController::class, 'getCurrent']);
        Route::post('subscriptions', [SubscriptionController::class, 'createForUser']);
        // Parameterized routes come after specific routes
        Route::get('subscriptions/{id}', [SubscriptionController::class, 'getUserSubscription']);
        Route::post('subscriptions/{id}/cancel-auto-renew', [SubscriptionController::class, 'cancelAutoRenew']);
        Route::post('subscriptions/{id}/resume-auto-renew', [SubscriptionController::class, 'resumeAutoRenew']);
        Route::post('subscriptions/{id}/upgrade', [SubscriptionController::class, 'upgradeUser']);

        // Payments/Transactions (user's transaction history)
        Route::get('payments', [\App\Http\Controllers\Billing\FinancialController::class, 'getUserPayments']);
    });
});
