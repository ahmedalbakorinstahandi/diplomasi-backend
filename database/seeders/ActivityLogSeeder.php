<?php

namespace Database\Seeders;

use App\Models\System\ActivityLog;
use App\Models\Users\User;
use Illuminate\Database\Seeder;

class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@demo.test')->first();

        ActivityLog::withTrashed()->updateOrCreate(
            ['user_id' => $admin?->id, 'action' => 'seed', 'description' => 'Database seeded with demo data'],
            [
                'ip_address' => '127.0.0.1',
                'deleted_at' => null,
            ]
        );

        $users = User::query()->where('email', 'like', 'user%@demo.test')->limit(5)->get();
        foreach ($users as $idx => $user) {
            ActivityLog::withTrashed()->updateOrCreate(
                ['user_id' => $user->id, 'action' => 'login', 'description' => 'User logged in (seed)'],
                [
                    'ip_address' => '192.168.1.' . (10 + $idx),
                    'deleted_at' => null,
                ]
            );
        }
    }
}

