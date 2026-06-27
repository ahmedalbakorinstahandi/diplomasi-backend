<?php

namespace App\Http\Permissions\Content;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class PagePermission
{
    public static function filterIndex($query)
    {
        self::canView();

        if (RequestContext::isApp()) {
            $query->where('is_published', true);
        }

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('page.view');
        }
    }

    public static function canShow($page): void
    {
        self::canView();

        if (RequestContext::isApp() && !$page->is_published) {
            MessageService::abort(404, 'messages.page.not_found');
        }
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('page.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('page.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('page.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}
