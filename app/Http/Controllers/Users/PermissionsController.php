<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Users\Permission;
use App\Models\Users\User;
use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;
use App\Services\ResponseService;

class PermissionsController extends Controller
{
    public function index()
    {
        if (!RequestContext::isDashboard()) {
            MessageService::abort(403, 'messages.permission.error');
        }

        // Dashboard gate
        AuthorizationService::authorize('admin.access');

        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $roles = $user->roles()->pluck('name')->values()->all();
        $effective = array_flip($user->getEffectivePermissionNames());

        $grouped = [];
        $allPermissions = Permission::query()->pluck('name')->values()->all();

        foreach ($allPermissions as $permName) {
            [$entity, $action] = array_pad(explode('.', $permName, 2), 2, null);
            if (!$entity || !$action) {
                continue;
            }

            if (!isset($grouped[$entity])) {
                $grouped[$entity] = [];
            }

            $grouped[$entity][$action] = isset($effective[$permName]);
        }

        return ResponseService::response([
            'status' => 200,
            'data' => [
                'roles' => $roles,
                'permissions' => $grouped,
            ],
        ]);
    }
}
