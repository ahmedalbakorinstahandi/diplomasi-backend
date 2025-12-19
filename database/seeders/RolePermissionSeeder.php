<?php

namespace Database\Seeders;

use App\Models\Users\Permission;
use App\Models\Users\Role;
use App\Models\Users\RolePermission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Attach minimal dashboard entry + RBAC management permissions to "admin" role.
     */
    public function run(): void
    {
        $adminRole = Role::withTrashed()->updateOrCreate(
            ['name' => 'admin'],
            [
                'description' => 'Dashboard admin role',
                'is_default' => false,
                'deleted_at' => null,
            ]
        );

        $permissionNames = [
            'admin.access',
            'permission.view',
            'role.view',
            'role.create',
            'role.update',
            'role.delete',
            'role.assign_permissions',
        ];

        $permissions = Permission::query()->whereIn('name', $permissionNames)->get();
        foreach ($permissions as $permission) {
            RolePermission::withTrashed()->updateOrCreate(
                ['role_id' => $adminRole->id, 'permission_id' => $permission->id],
                ['deleted_at' => null]
            );
        }
    }
}
