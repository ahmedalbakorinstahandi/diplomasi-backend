<?php

namespace Database\Seeders;

use App\Models\Users\Role;
use App\Models\Users\User;
use App\Models\Users\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure extra dashboard roles exist (besides user/super_admin/admin)
        $roles = [
            ['name' => 'editor', 'description' => 'Content editor', 'is_default' => false],
            ['name' => 'learning_manager', 'description' => 'Learning content manager', 'is_default' => false],
            ['name' => 'support', 'description' => 'Support agent', 'is_default' => false],
        ];

        foreach ($roles as $role) {
            Role::withTrashed()->updateOrCreate(
                ['name' => $role['name']],
                [
                    'description' => $role['description'],
                    'is_default' => $role['is_default'],
                    'deleted_at' => null,
                ]
            );
        }

        $userRole = Role::where('name', 'user')->first();
        $adminRole = Role::where('name', 'admin')->first();
        $superAdminRole = Role::where('name', 'super_admin')->first();

        // Super admin (dashboard bypass)
        $superAdmin = User::withTrashed()->updateOrCreate(
            ['email' => 'superadmin@demo.test'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'avatar' => 'https://i.pravatar.cc/80?img=1',
                'phone' => '+201000000001',
                'phone_verified' => true,
                'email_verified' => true,
                'password' => Hash::make('Password123!'),
                'language' => 'ar',
                'status' => 'active',
                'deleted_at' => null,
            ]
        );

        if ($superAdminRole) {
            UserRole::withTrashed()->updateOrCreate(
                ['user_id' => $superAdmin->id, 'role_id' => $superAdminRole->id],
                ['deleted_at' => null]
            );
        }

        // Dashboard admin (role-based)
        $dashboardAdmin = User::withTrashed()->updateOrCreate(
            ['email' => 'admin@demo.test'],
            [
                'first_name' => 'Dashboard',
                'last_name' => 'Admin',
                'avatar' => 'https://i.pravatar.cc/80?img=2',
                'phone' => '+201000000002',
                'phone_verified' => true,
                'email_verified' => true,
                'password' => Hash::make('Password123!'),
                'language' => 'ar',
                'status' => 'active',
                'deleted_at' => null,
            ]
        );

        if ($adminRole) {
            UserRole::withTrashed()->updateOrCreate(
                ['user_id' => $dashboardAdmin->id, 'role_id' => $adminRole->id],
                ['deleted_at' => null]
            );
        }

        // Demo app users
        for ($i = 1; $i <= 50; $i++) {
            $email = sprintf('user%02d@demo.test', $i);
            $phone = '+201' . str_pad((string) (100000000 + $i), 9, '0', STR_PAD_LEFT);

            $user = User::withTrashed()->updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => 'User',
                    'last_name' => sprintf('%02d', $i),
                    'avatar' => 'https://i.pravatar.cc/80?img=' . ((($i + 2) % 70) + 1),
                    'phone' => $phone,
                    'phone_verified' => $i % 3 !== 0,
                    'email_verified' => $i % 4 !== 0,
                    'password' => Hash::make('Password123!'),
                    'language' => 'ar',
                    'status' => 'active',
                    'deleted_at' => null,
                ]
            );

            if ($userRole) {
                UserRole::withTrashed()->updateOrCreate(
                    ['user_id' => $user->id, 'role_id' => $userRole->id],
                    ['deleted_at' => null]
                );
            }
        }
    }
}

