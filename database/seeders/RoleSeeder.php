<?php

namespace Database\Seeders;

use App\Models\Users\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::withTrashed()->updateOrCreate(
            ['name' => 'user'],
            [
                'description' => 'User role',
                'is_default' => true,
                'deleted_at' => null,
            ]
        );

        Role::withTrashed()->updateOrCreate(
            ['name' => 'super_admin'],
            [
                'description' => 'Super admin role',
                'is_default' => false,
                'deleted_at' => null,
            ]
        );

        // run this seeder in next command
        // php artisan db:seed --class=RoleSeeder
    }
}
