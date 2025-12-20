<?php

namespace App\Http\Permissions\Content;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class ArticlePermission
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
            AuthorizationService::authorize('article.view');
        }
    }

    public static function canShow($article): void
    {
        self::canView();

        if (RequestContext::isApp() && !$article->is_published) {
            MessageService::abort(404, 'messages.article.not_found');
        }
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('article.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('article.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('article.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canReorder(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('article.reorder');
            return;
        }
    }
}
