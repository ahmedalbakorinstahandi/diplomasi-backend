<?php

use App\Http\Controllers\Billing\AppleIapController;
use App\Http\Controllers\Billing\PlanController;
use App\Http\Controllers\Billing\MoyasarPaymentController;
use App\Http\Controllers\Billing\PaymentMethodController;
use App\Http\Controllers\Billing\BillingHistoryController;
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

    //Setting
    Route::get('settings', [SettingController::class, 'index']);
    Route::get('settings/{idOrKey}', [SettingController::class, 'show']);



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
        Route::get('lessons/{lessonId}/attempts', [LessonController::class, 'listAttempts']);
        Route::get('lessons/{lessonId}/attempts/{attemptId}/review', [LessonController::class, 'reviewAttempt']);
        Route::get('lessons/{lessonId}/questions', [LessonController::class, 'getQuestions']);
        Route::get('lessons/{lessonId}/attempts/{attemptId}/current-question', [LessonController::class, 'getCurrentQuestion']);
        Route::post('lessons/{lessonId}/attempts/{attemptId}/submit-answer', [LessonController::class, 'submitAnswer']);
        Route::post('lessons/{lessonId}/attempts/{attemptId}/finish', [LessonController::class, 'finishAttempt']);
        Route::post('lessons/{lessonId}/attempts/{attemptId}/mark-video-watched', [LessonController::class, 'markVideoWatched']);

        // Scenarios - User actions
        Route::post('scenarios/{id}/start-attempt', [ScenarioController::class, 'startAttempt']);
        Route::get('scenarios/{id}/attempts', [ScenarioController::class, 'listAttempts']);
        Route::get('scenarios/{id}/attempts/{attemptId}/journey', [ScenarioController::class, 'attemptJourney']);
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

        // Billing
        Route::post('billing/payments/verify', [MoyasarPaymentController::class, 'verify']);
        Route::get('billing/payment-methods', [PaymentMethodController::class, 'index']);
        Route::post('billing/payment-methods', [PaymentMethodController::class, 'store']);
        Route::post('billing/payment-methods/{id}/set-default', [PaymentMethodController::class, 'setDefault']);
        Route::delete('billing/payment-methods/{id}', [PaymentMethodController::class, 'destroy']);
        Route::get('billing/invoices', [BillingHistoryController::class, 'invoices']);
        Route::get('billing/invoices/{id}', [BillingHistoryController::class, 'showInvoice']);
        Route::get('billing/invoices/{id}/download', [BillingHistoryController::class, 'downloadInvoice']);
        Route::get('billing/payments', [BillingHistoryController::class, 'payments']);
        Route::get('billing/subscription', [SubscriptionController::class, 'current']);
        Route::post('billing/subscription/purchase', [SubscriptionController::class, 'purchasePlan']);
        Route::post('billing/subscription/purchase-with-payment', [SubscriptionController::class, 'purchasePlanWithPayment']);
        Route::post('billing/subscription/cancel', [SubscriptionController::class, 'cancelAtPeriodEnd']);
        Route::post('billing/subscription/resume', [SubscriptionController::class, 'resumeAutoRenew']);
        Route::post('billing/subscription/retry-payment', [SubscriptionController::class, 'retryPayment']);
        // Apple IAP
        Route::post('ios/purchase/verify', [AppleIapController::class, 'verify']);
    });
});
