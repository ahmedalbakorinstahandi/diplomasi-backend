<?php

namespace App\Http\Permissions\System;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class ReengagementReminderPermission
{
    public static function filterIndex($query)
    {
        self::canView();
        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('reengagement_reminder.view');
            return;
        }
        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canShow($reminder): void
    {
        self::canView();
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('reengagement_reminder.create');
            return;
        }
        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('reengagement_reminder.update');
            return;
        }
        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('reengagement_reminder.delete');
            return;
        }
        MessageService::abort(403, 'messages.permission.error');
    }
}
