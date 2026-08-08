<?php

namespace App\Providers;

use App\Events\UserCourseCompleted;
use App\Events\UserLevelCompleted;
use App\Listeners\CheckCertificateEligibility;
use App\Listeners\SendCourseCompletedNotification;
use App\Listeners\SendLevelCompletedNotification;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderInterface;
use App\Services\AiNegotiator\Llm\LlmProviderFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmProviderInterface::class, function () {
            return LlmProviderFactory::make();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Event Listeners
        Event::listen(
            UserLevelCompleted::class,
            CheckCertificateEligibility::class
        );
        Event::listen(
            UserLevelCompleted::class,
            SendLevelCompletedNotification::class
        );

        Event::listen(
            UserCourseCompleted::class,
            CheckCertificateEligibility::class
        );
        Event::listen(
            UserCourseCompleted::class,
            SendCourseCompletedNotification::class
        );
    }
}
