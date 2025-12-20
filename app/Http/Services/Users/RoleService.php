<?php

namespace App\Http\Services\Users;

use App\Models\Users\Permission;
use App\Models\Users\Role;
use App\Models\Users\RolePermission;
use App\Services\FilterService;
use App\Services\MessageService;

class RoleService
{
    public function index($filters = [])
    {
        $query = Role::query()->with(['permissions']);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'id';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = ['name', 'description'];
        $numericFields = [];
        $dateFields = ['created_at'];
        $exactMatchFields = ['id', 'is_default'];
        $inFields = ['is_default'];

        return FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );
    }

    public function show(int $id): Role
    {
        $role = Role::where('id', $id)->first();

        if (!$role) {
            MessageService::abort(404, 'messages.role.not_found');
        }

        $role->load(['permissions', 'rolePermissions']);

        return $role;
    }

    public function create(array $data): Role
    {
        $existingRole = Role::where('name', $data['name'])->first();
        if ($existingRole) {
            MessageService::abort(400, 'messages.role.already_exists');
        }

        $role = Role::create($data);

        return $this->show($role->id);
    }

    public function update(array $data, Role $role): Role
    {
        if (isset($data['name'])) {
            $existingRole = Role::where('name', $data['name'])->where('id', '!=', $role->id)->first();
            if ($existingRole) {
                MessageService::abort(400, 'messages.role.already_exists');
            }
        }

        $role->update($data);

        return $this->show($role->id);
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    /**
     * Sync role permissions by permission names.
     * Uses soft-delete behavior on role_permissions pivot.
     */
    public function syncPermissions(Role $role, array $permissionNames): Role
    {
        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->values()
            ->all();

        $currentPermissionIds = RolePermission::query()
            ->where('role_id', $role->id)
            ->whereNull('deleted_at')
            ->pluck('permission_id')
            ->values()
            ->all();

        $toAdd = array_values(array_diff($permissionIds, $currentPermissionIds));
        $toRemove = array_values(array_diff($currentPermissionIds, $permissionIds));

        foreach ($toAdd as $permissionId) {
            RolePermission::withTrashed()->updateOrCreate(
                [
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                ],
                [
                    'deleted_at' => null,
                ]
            );
        }

        if (!empty($toRemove)) {
            RolePermission::query()
                ->where('role_id', $role->id)
                ->whereIn('permission_id', $toRemove)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        }

        return $this->show($role->id);
    }
}
