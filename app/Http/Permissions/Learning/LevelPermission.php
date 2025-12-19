<?php

namespace App\Http\Permissions\Learning;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class LevelPermission
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
            AuthorizationService::authorize('level.view');
        }
    }

    public static function canShow($level): void
    {
        self::canView();

        if (RequestContext::isApp() && !$level->is_published) {
            MessageService::abort(404, 'messages.level.not_found');
        }
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('level.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('level.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('level.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canReorder(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('level.reorder');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}

