<?php

namespace App\Http\Permissions\Scenarios;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;
use App\Models\Users\User;

class ScenarioPermission
{
    public static function filterIndex($query)
    {
        self::canView();

        if (RequestContext::isApp()) {
            $query->where('is_published', true);
        }

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('scenario.view');
        }
    }

    public static function canShow($scenario): void
    {
        self::canView();

        if (RequestContext::isApp() && !$scenario->is_published) {
            MessageService::abort(404, 'messages.scenario.not_found');
        }
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('scenario.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('scenario.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('scenario.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canReorder(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('scenario.reorder');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canStartAttempt(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('scenario.start_attempt');
            return;
        }

        // App context: must be authenticated.
        if (!User::auth()) {
            MessageService::abort(401, 'messages.unauthorized');
        }
    }

    public static function canSubmitAnswer(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('scenario.submit_answer');
            return;
        }

        if (!User::auth()) {
            MessageService::abort(401, 'messages.unauthorized');
        }
    }
}

