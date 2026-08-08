<?php

namespace App\Http\Permissions\AiNegotiator;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class AiNegotiatorSettingsPermission
{
    public static function canManage(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('ai_negotiator_settings.manage');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}
