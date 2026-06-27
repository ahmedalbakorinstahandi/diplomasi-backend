<?php

use App\Http\Controllers\Billing\AdminSubscriptionController;
use App\Http\Controllers\Billing\PlanController;
use App\Http\Controllers\Content\ArticleController;
use App\Http\Controllers\Content\ContactMessageController;
use App\Http\Controllers\Content\FaqController;
use App\Http\Controllers\Content\PageController;
use App\Http\Controllers\Content\PodcastAdminController;
use App\Http\Controllers\Learning\CourseController;
use App\Http\Controllers\Learning\GlossaryTermController;
use App\Http\Controllers\Learning\LessonController;
use App\Http\Controllers\Learning\LessonQuestionController;
use App\Http\Controllers\Learning\LevelCertificateTemplateController;
use App\Http\Controllers\Learning\LevelController;
use App\Http\Controllers\Learning\LevelTrackController;
use App\Http\Controllers\Scenarios\ScenarioController;
use App\Http\Controllers\Scenarios\ScenarioQuestionController;
use App\Http\Controllers\System\CertificateController;
use App\Http\Controllers\System\NotificationController;
use App\Http\Controllers\System\ReengagementReminderController;
use App\Http\Controllers\System\SettingController;
use App\Http\Controllers\Users\PermissionListController;
use App\Http\Controllers\Users\RoleController;
use App\Http\Controllers\Users\UserController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\TouchUserActivityMiddleware;
use Illuminate\Support\Facades\Route;

// Admin routes
Route::group(['prefix' => 'admin'], function () {

    Route::group(['middleware' => ['auth:sanctum', AdminMiddleware::class, TouchUserActivityMiddleware::class]], function () {

        // Users
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{id}', [UserController::class, 'show']);
        Route::get('users/{id}/certificates', [UserController::class, 'certificates']);
        Route::post('users', [UserController::class, 'create']);
        Route::put('users/{id}', [UserController::class, 'update']);
        Route::delete('users/{id}', [UserController::class, 'delete']);
        Route::put('users/{id}/roles', [UserController::class, 'syncRoles']);

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

        // Lesson Questions (admin management)
        Route::get('lesson-questions', [LessonQuestionController::class, 'index']);
        Route::get('lesson-questions/{id}', [LessonQuestionController::class, 'show']);
        Route::post('lesson-questions', [LessonQuestionController::class, 'create']);
        Route::put('lesson-questions/{id}', [LessonQuestionController::class, 'update']);
        Route::delete('lesson-questions/{id}', [LessonQuestionController::class, 'delete']);
        Route::put('lesson-questions/{id}/reorder', [LessonQuestionController::class, 'reorder']);

        // Import all questions for a specific lesson (JSON)
        Route::post('lessons/{id}/import-questions', [LessonController::class, 'importQuestions']);

        // Levels
        Route::get('levels', [LevelController::class, 'index']);
        Route::get('levels/{id}', [LevelController::class, 'show']);
        Route::post('levels', [LevelController::class, 'create']);
        Route::put('levels/{id}', [LevelController::class, 'update']);
        Route::delete('levels/{id}', [LevelController::class, 'delete']);
        Route::put('levels/{id}/reorder', [LevelController::class, 'reorder']);
        Route::post('levels/{id}/certificate-template', [LevelCertificateTemplateController::class, 'upload']);
        Route::delete('levels/{id}/certificate-template', [LevelCertificateTemplateController::class, 'destroy']);
        Route::put('levels/{id}/certificate-template-config', [LevelCertificateTemplateController::class, 'updateConfig']);
        Route::get('levels/{id}/certificate-template-preview', [LevelCertificateTemplateController::class, 'preview']);

        // Level Tracks
        Route::get('level-tracks', [LevelTrackController::class, 'index']);
        // Route::get('level-tracks/{id}', [LevelTrackController::class, 'show']);
        // Route::post('level-tracks', [LevelTrackController::class, 'create']);
        // Route::put('level-tracks/{id}', [LevelTrackController::class, 'update']);
        // Route::delete('level-tracks/{id}', [LevelTrackController::class, 'delete']);
        Route::put('level-tracks/{id}/reorder', [LevelTrackController::class, 'reorder']);
        // Route::post('levels/{levelId}/sync-level-tracks', [LevelTrackController::class, 'syncForLevel']);

        // Scenarios
        Route::get('scenarios', [ScenarioController::class, 'index']);
        Route::get('scenarios/{id}', [ScenarioController::class, 'show']);
        Route::post('scenarios/import', [ScenarioController::class, 'importFull']);
        Route::post('scenarios/minimal', [ScenarioController::class, 'createMinimal']);
        Route::post('scenarios', [ScenarioController::class, 'create']);
        Route::put('scenarios/{id}', [ScenarioController::class, 'update']);
        Route::delete('scenarios/{id}', [ScenarioController::class, 'delete']);
        Route::put('scenarios/{id}/reorder', [ScenarioController::class, 'reorder']);
        Route::post('scenarios/{id}/import-content', [ScenarioController::class, 'importContent']);

        // Scenario Questions
        Route::get('scenario-questions', [ScenarioQuestionController::class, 'index']);
        Route::get('scenario-questions/{id}', [ScenarioQuestionController::class, 'show']);
        Route::post('scenario-questions', [ScenarioQuestionController::class, 'create']);
        Route::put('scenario-questions/{id}', [ScenarioQuestionController::class, 'update']);
        Route::delete('scenario-questions/{id}', [ScenarioQuestionController::class, 'delete']);
        Route::put('scenario-questions/{id}/reorder', [ScenarioQuestionController::class, 'reorder']);
        Route::get('scenario-questions/validate-flow/check', [ScenarioQuestionController::class, 'validateFlow']);

        // Level Tracks
        Route::get('level-tracks', [LevelTrackController::class, 'index']);
        Route::get('level-tracks/{id}', [LevelTrackController::class, 'show']);
        Route::post('level-tracks', [LevelTrackController::class, 'create']);
        Route::put('level-tracks/{id}', [LevelTrackController::class, 'update']);
        Route::delete('level-tracks/{id}', [LevelTrackController::class, 'delete']);
        Route::put('level-tracks/{id}/reorder', [LevelTrackController::class, 'reorder']);
        Route::post('levels/{levelId}/sync-level-tracks', [LevelTrackController::class, 'syncForLevel']);

        // Glossary Terms
        Route::get('glossary-terms', [GlossaryTermController::class, 'index']);
        Route::get('glossary-terms/{id}', [GlossaryTermController::class, 'show']);
        Route::post('glossary-terms', [GlossaryTermController::class, 'create']);
        Route::put('glossary-terms/{id}', [GlossaryTermController::class, 'update']);
        Route::delete('glossary-terms/{id}', [GlossaryTermController::class, 'delete']);
        Route::put('glossary-terms/{id}/reorder', [GlossaryTermController::class, 'reorder']);

        // Articles
        Route::get('articles', [ArticleController::class, 'index']);
        Route::get('articles/{id}', [ArticleController::class, 'show']);
        Route::post('articles', [ArticleController::class, 'create']);
        Route::put('articles/{id}', [ArticleController::class, 'update']);
        Route::delete('articles/{id}', [ArticleController::class, 'delete']);
        Route::put('articles/{id}/reorder', [ArticleController::class, 'reorder']);

        // CMS Pages
        Route::get('pages', [PageController::class, 'index']);
        Route::get('pages/{id}', [PageController::class, 'show']);
        Route::post('pages', [PageController::class, 'create']);
        Route::put('pages/{id}', [PageController::class, 'update']);
        Route::delete('pages/{id}', [PageController::class, 'delete']);

        // Contact messages
        Route::get('contact-messages', [ContactMessageController::class, 'index']);
        Route::get('contact-messages/{id}', [ContactMessageController::class, 'show']);
        Route::put('contact-messages/{id}/mark-read', [ContactMessageController::class, 'markAsRead']);

        // Podcasts
        Route::get('podcasts/stats', [PodcastAdminController::class, 'globalStats']);
        Route::get('podcasts', [PodcastAdminController::class, 'index']);
        Route::get('podcasts/{id}', [PodcastAdminController::class, 'show']);
        Route::post('podcasts', [PodcastAdminController::class, 'create']);
        Route::put('podcasts/{id}', [PodcastAdminController::class, 'update']);
        Route::delete('podcasts/{id}', [PodcastAdminController::class, 'delete']);
        Route::post('podcasts/{id}/restore', [PodcastAdminController::class, 'restore']);
        Route::put('podcasts/{id}/publish', [PodcastAdminController::class, 'togglePublish']);
        Route::put('podcasts/{id}/reorder', [PodcastAdminController::class, 'reorder']);
        Route::get('podcasts/{id}/stats', [PodcastAdminController::class, 'stats']);

        // FAQs
        Route::get('faqs', [FaqController::class, 'index']);
        Route::get('faqs/{id}', [FaqController::class, 'show']);
        Route::post('faqs', [FaqController::class, 'create']);
        Route::put('faqs/{id}', [FaqController::class, 'update']);
        Route::delete('faqs/{id}', [FaqController::class, 'delete']);
        Route::put('faqs/{id}/reorder', [FaqController::class, 'reorder']);

        // Plans
        Route::get('plans', [PlanController::class, 'index']);
        Route::get('plans/{id}', [PlanController::class, 'show']);
        Route::post('plans', [PlanController::class, 'create']);
        Route::put('plans/{id}', [PlanController::class, 'update']);
        Route::delete('plans/{id}', [PlanController::class, 'delete']);
        Route::put('plans/{id}/reorder', [PlanController::class, 'reorder']);

        // Subscriptions (admin)
        Route::get('subscriptions', [AdminSubscriptionController::class, 'index']);
        Route::get('subscriptions/{id}', [AdminSubscriptionController::class, 'show']);
        Route::post('subscriptions', [AdminSubscriptionController::class, 'create']);
        Route::put('subscriptions/{id}', [AdminSubscriptionController::class, 'update']);
        Route::delete('subscriptions/{id}', [AdminSubscriptionController::class, 'delete']);
        Route::post('subscriptions/{id}/cancel', [AdminSubscriptionController::class, 'cancel']);
        Route::post('subscriptions/{id}/renew', [AdminSubscriptionController::class, 'renew']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/{id}', [NotificationController::class, 'show']);
        Route::post('notifications', [NotificationController::class, 'create']);
        Route::put('notifications/{id}', [NotificationController::class, 'update']);
        Route::delete('notifications/{id}', [NotificationController::class, 'delete']);

        // Re-engagement reminders (تذكيرات العودة)
        Route::get('reengagement-reminders', [ReengagementReminderController::class, 'index']);
        Route::get('reengagement-reminders/{id}', [ReengagementReminderController::class, 'show']);
        Route::post('reengagement-reminders', [ReengagementReminderController::class, 'create']);
        Route::put('reengagement-reminders/{id}', [ReengagementReminderController::class, 'update']);
        Route::put('reengagement-reminders/{id}/reorder', [ReengagementReminderController::class, 'reorder']);
        Route::delete('reengagement-reminders/{id}', [ReengagementReminderController::class, 'delete']);

        // Settings
        Route::get('settings', [SettingController::class, 'index']);
        Route::get('settings/{idOrKey}', [SettingController::class, 'show']);
        Route::post('settings', [SettingController::class, 'create']);
        Route::put('settings', [SettingController::class, 'updateMany']);
        Route::put('settings/{idOrKey}', [SettingController::class, 'update']);
        Route::delete('settings/{idOrKey}', [SettingController::class, 'delete']);

        // Certificates
        Route::get('certificates', [CertificateController::class, 'index']);
        Route::get('certificates/{id}', [CertificateController::class, 'show']);
        Route::post('certificates/issue', [CertificateController::class, 'issue']);
        Route::post('certificates/{id}/regenerate', [CertificateController::class, 'regenerate']);
        Route::post('certificates/{id}/revoke', [CertificateController::class, 'revoke']);

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
