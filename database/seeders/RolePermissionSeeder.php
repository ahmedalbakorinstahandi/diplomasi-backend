<?php

namespace Database\Seeders;

use App\Models\Users\Permission;
use App\Models\Users\Role;
use App\Models\Users\RolePermission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
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

        // Grant admin role ALL available permissions so dashboard endpoints work out of the box.
        Permission::query()->get()->each(function (Permission $permission) use ($adminRole) {
            RolePermission::withTrashed()->updateOrCreate(
                ['role_id' => $adminRole->id, 'permission_id' => $permission->id],
                ['deleted_at' => null]
            );
        });
    }
}
