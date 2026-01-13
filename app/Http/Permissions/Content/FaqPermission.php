<?php

namespace App\Http\Permissions\Content;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class FaqPermission
{
    public static function filterIndex($query)
    {
        self::canView();

        // App/guest context: all FAQs are visible (public)
        // No additional filtering needed

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('faq.view');
        }
    }

    public static function canShow($faq): void
    {
        self::canView();

        // FAQs are public, no additional checks needed
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('faq.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('faq.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('faq.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canReorder(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('faq.reorder');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}
