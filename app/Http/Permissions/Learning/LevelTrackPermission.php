<?php

namespace App\Http\Permissions\Learning;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class LevelTrackPermission
{
    public static function filterIndex($query)
    {
        self::canView();

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('level_track.view');
        }
    }

    public static function canShow($levelTrack): void
    {
        self::canView();
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('level_track.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('level_track.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('level_track.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canReorder(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('level_track.reorder');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}

