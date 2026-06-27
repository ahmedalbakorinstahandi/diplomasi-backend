<?php

namespace App\Http\Permissions\Billing;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class InvoicePermission
{
    public static function filterIndex($query)
    {
        self::canView();
        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('invoice.view');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canShow(): void
    {
        self::canView();
    }

    public static function canDownload(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('invoice.download');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}
