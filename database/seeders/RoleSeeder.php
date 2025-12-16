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
        Role::create([
            'name' => 'user',
            'description' => 'User role',
            'is_default' => true,
        ]);

        Role::create([
            'name' => 'super_admin',
            'description' => 'Super admin role',
            'is_default' => false,
        ]);

        // run this seeder in next command
        // php artisan db:seed --class=RoleSeeder
    }
}
