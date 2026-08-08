<?php

namespace App\Http\Permissions\AiNegotiator;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class AiNegotiatorKnowledgePermission
{
    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('ai_negotiator_knowledge.view');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canManage(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('ai_negotiator_knowledge.manage');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}
