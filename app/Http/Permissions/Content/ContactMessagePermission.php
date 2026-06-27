<?php

namespace App\Http\Permissions\Content;

use App\Services\AuthorizationService;
use App\Services\RequestContext;

class ContactMessagePermission
{
    public static function filterIndex($query)
    {
        self::canView();

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('contact_message.view');
        }
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('contact_message.update');
            return;
        }

        abort(403);
    }
}
