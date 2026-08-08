<?php

namespace App\Http\Permissions\AiNegotiator;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class AiNegotiatorSessionPermission
{
    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('ai_negotiator_session.view');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canManage(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('ai_negotiator_session.manage');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}
