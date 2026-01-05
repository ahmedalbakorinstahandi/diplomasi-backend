<?php

namespace App\Http\Permissions\Learning;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class GlossaryTermPermission
{
    public static function filterIndex($query)
    {
        self::canView();

        // App/guest context: all glossary terms are visible (public)
        // No additional filtering needed

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('glossary_term.view');
        }
    }

    public static function canShow($glossaryTerm): void
    {
        self::canView();

        // Glossary terms are public, no additional checks needed
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('glossary_term.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('glossary_term.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('glossary_term.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canReorder(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('glossary_term.reorder');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}

