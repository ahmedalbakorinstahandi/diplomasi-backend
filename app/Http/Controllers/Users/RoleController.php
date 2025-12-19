<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Users\RoleManagementPermission;
use App\Http\Requests\Users\CreateRoleRequest;
use App\Http\Requests\Users\SyncRolePermissionsRequest;
use App\Http\Requests\Users\UpdateRoleRequest;
use App\Http\Resources\Users\RoleResource;
use App\Http\Services\Users\RoleService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    protected $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function index(Request $request)
    {
        RoleManagementPermission::canViewRoles();

        $roles = $this->roleService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'data' => $roles,
            'meta' => true,
            'resource' => RoleResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        RoleManagementPermission::canViewRoles();

        $role = $this->roleService->show($id);

        return ResponseService::response([
            'success' => true,
            'data' => $role,
            'resource' => RoleResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateRoleRequest $request)
    {
        RoleManagementPermission::canCreateRole();

        $role = $this->roleService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $role,
            'message' => 'messages.role.created',
            'status' => 201,
            'resource' => RoleResource::class,
        ]);
    }

    public function update(UpdateRoleRequest $request, int $id)
    {
        RoleManagementPermission::canUpdateRole();

        $role = $this->roleService->show($id);
        $role = $this->roleService->update($request->validated(), $role);

        return ResponseService::response([
            'success' => true,
            'data' => $role,
            'message' => 'messages.role.updated',
            'status' => 200,
            'resource' => RoleResource::class,
        ]);
    }

    public function delete(int $id)
    {
        RoleManagementPermission::canDeleteRole();

        $role = $this->roleService->show($id);
        $this->roleService->delete($role);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.role.deleted',
            'status' => 200,
        ]);
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, int $id)
    {
        RoleManagementPermission::canAssignPermissions();

        $role = $this->roleService->show($id);
        $role = $this->roleService->syncPermissions($role, $request->validated()['permission_names'] ?? []);

        return ResponseService::response([
            'success' => true,
            'data' => $role,
            'message' => 'messages.role.permissions_synced',
            'status' => 200,
            'resource' => RoleResource::class,
        ]);
    }
}

