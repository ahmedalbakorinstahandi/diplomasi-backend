<?php

namespace App\Http\Permissions\Negotiation;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class NegotiationLevelPermission
{
    public static function filterIndex($query)
    {
        self::canView();

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('negotiation_level.view');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('negotiation_level.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('negotiation_level.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('negotiation_level.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canReorder(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('negotiation_level.reorder');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}
