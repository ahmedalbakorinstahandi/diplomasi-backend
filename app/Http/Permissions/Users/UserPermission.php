<?php

namespace App\Http\Permissions\Users;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;
use App\Models\Users\User;

class UserPermission
{
    public static function filterIndex($query)
    {
        self::canView();

        if (RequestContext::isApp()) {
            $user = User::auth();
            if (!$user) {
                MessageService::abort(401, 'messages.unauthorized');
            }

            // App context: only own user record.
            $query->where('id', $user->id);
        }

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('user.view');
            return;
        }

        if (!User::auth()) {
            MessageService::abort(401, 'messages.unauthorized');
        }
    }

    public static function canShow($targetUser): void
    {
        self::canView();

        if (RequestContext::isApp()) {
            $user = User::auth();
            if ($targetUser->id !== $user->id) {
                MessageService::abort(403, 'messages.permission.error');
            }
        }
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('user.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate($targetUser = null): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('user.update');
            return;
        }

        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        if ($targetUser && $targetUser->id !== $user->id) {
            MessageService::abort(403, 'messages.permission.error');
        }
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('user.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}

