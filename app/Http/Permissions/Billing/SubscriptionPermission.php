<?php

namespace App\Http\Permissions\Billing;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class SubscriptionPermission
{
    public static function filterIndex($query)
    {
        self::canView();
        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('subscription.view');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canShow(): void
    {
        self::canView();
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('subscription.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('subscription.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('subscription.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canCancel(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('subscription.cancel');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canRenew(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('subscription.renew');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpgrade(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('subscription.upgrade');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}

