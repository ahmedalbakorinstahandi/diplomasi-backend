<?php

namespace App\Http\Permissions\Billing;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class PlanPermission
{
    public static function filterIndex($query)
    {
        self::canView();

        // App/guest context: all plans are visible (public)
        // No additional filtering needed

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('plan.view');
        }
    }

    public static function canShow($plan): void
    {
        self::canView();

        // Plans are public, no additional checks needed
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('plan.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('plan.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('plan.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canReorder(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('plan.reorder');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}
