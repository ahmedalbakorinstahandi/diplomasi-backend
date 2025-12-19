<?php

namespace App\Http\Permissions\Users;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class RoleManagementPermission
{
    public static function canViewRoles(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('role.view');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canCreateRole(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('role.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdateRole(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('role.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDeleteRole(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('role.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canAssignPermissions(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('role.assign_permissions');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canViewPermissions(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('permission.view');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}

