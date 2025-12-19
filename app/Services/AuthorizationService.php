<?php

namespace App\Services;

use App\Models\Users\Permission;
use App\Models\Users\User;

class AuthorizationService
{
    /**
     * Centralized, DB-driven authorization.
     *
     * Rules:
     * - Unauthenticated: 401
     * - super_admin: allow everything
     * - Permission must exist in DB (fail fast if missing)
     * - Otherwise require user to have permission via any role
     */
    public static function authorize(string $permissionName): void
    {
        // This service is dashboard-only. App requests must not use admin permissions.
        if (RequestContext::isApp()) {
            MessageService::abort(403, 'messages.permission.error');
        }

        $user = User::auth();

        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        // Super admin bypasses everything
        if ($user->hasRole('super_admin')) {
            return;
        }

        // Fail fast if permission is not stored in DB (developer/config error)
        $exists = Permission::query()->where('name', $permissionName)->exists();
        if (!$exists) {
            throw new \RuntimeException("Missing permission in database: {$permissionName}");
        }

        if ($user->hasPermission($permissionName)) {
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}
