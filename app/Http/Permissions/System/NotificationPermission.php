<?php

namespace App\Http\Permissions\System;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;
use App\Models\Users\User;

class NotificationPermission
{
    public static function filterIndex($query)
    {
        self::canView();

        // App context: only own notifications (and optionally global/null user_id).
        if (RequestContext::isApp()) {
            $user = User::auth();
            if (!$user) {
                MessageService::abort(401, 'messages.unauthorized');
            }

            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhereNull('user_id');
            });
        }

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('notification.view');
            return;
        }

        // App context: must be authenticated.
        if (!User::auth()) {
            MessageService::abort(401, 'messages.unauthorized');
        }
    }

    public static function canShow($notification): void
    {
        self::canView();

        if (RequestContext::isApp()) {
            $user = User::auth();
            if ($notification->user_id && $notification->user_id !== $user->id) {
                MessageService::abort(403, 'messages.permission.error');
            }
        }
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('notification.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('notification.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('notification.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canMarkAsRead($notification): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('notification.mark_as_read');
            return;
        }

        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        if ($notification->user_id && $notification->user_id !== $user->id) {
            MessageService::abort(403, 'messages.permission.error');
        }
    }

    public static function canMarkAllAsRead(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('notification.mark_all_as_read');
            return;
        }

        if (!User::auth()) {
            MessageService::abort(401, 'messages.unauthorized');
        }
    }

    public static function canUnreadCount(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('notification.unread_count');
            return;
        }

        if (!User::auth()) {
            MessageService::abort(401, 'messages.unauthorized');
        }
    }
}

