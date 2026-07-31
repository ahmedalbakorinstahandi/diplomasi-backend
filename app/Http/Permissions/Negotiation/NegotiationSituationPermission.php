<?php

namespace App\Http\Permissions\Negotiation;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class NegotiationSituationPermission
{
    public static function filterIndex($query)
    {
        self::canView();

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('negotiation_situation.view');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('negotiation_situation.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('negotiation_situation.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('negotiation_situation.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canReorder(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('negotiation_situation.reorder');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}
