<?php

namespace App\Http\Permissions\Progress;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;
use App\Models\Users\User;

class ProgressPermission
{
    public static function filterIndex($query)
    {
        self::canView();

        if (RequestContext::isApp()) {
            $user = User::auth();
            if (!$user) {
                MessageService::abort(401, 'messages.unauthorized');
            }

            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('progress.view');
            return;
        }

        if (!User::auth()) {
            MessageService::abort(401, 'messages.unauthorized');
        }
    }

    public static function canShow($progress): void
    {
        self::canView();

        if (RequestContext::isApp()) {
            $user = User::auth();
            if ($progress->user_id !== $user->id) {
                MessageService::abort(403, 'messages.permission.error');
            }
        }
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('progress.create');
            return;
        }

        if (!User::auth()) {
            MessageService::abort(401, 'messages.unauthorized');
        }
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('progress.update');
            return;
        }

        if (!User::auth()) {
            MessageService::abort(401, 'messages.unauthorized');
        }
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('progress.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}

