<?php

namespace App\Http\Permissions\Content;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class PodcastPermission
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
            AuthorizationService::authorize('podcast.view');
        }
    }

    public static function canShow($podcast): void
    {
        self::canView();

        if (RequestContext::isApp() && ! $podcast->is_published) {
            MessageService::abort(404, 'messages.podcast.not_found');
        }
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('podcast.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('podcast.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('podcast.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canRestore(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('podcast.restore');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canPublish(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('podcast.publish');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canReorder(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('podcast.reorder');
        }
    }
}
