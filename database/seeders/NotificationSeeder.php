<?php

namespace Database\Seeders;

use App\Models\System\Notification;
use App\Models\Users\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        // Global notification
        Notification::withTrashed()->updateOrCreate(
            ['user_id' => null, 'title' => 'مرحباً بك في Diplomasi'],
            [
                'body' => 'تم تجهيز بيانات تجريبية كاملة لتستطيع تجربة النظام بسهولة.',
                'type' => 'system',
                'data' => ['source' => 'seed'],
                'read_at' => null,
                'deleted_at' => null,
            ]
        );

        $users = User::query()
            ->where('email', 'like', 'user%@demo.test')
            ->orderBy('id')
            ->limit(10)
            ->get();

        foreach ($users as $idx => $user) {
            Notification::withTrashed()->updateOrCreate(
                ['user_id' => $user->id, 'title' => 'تحديث تقدمك'],
                [
                    'body' => 'لديك دروس جديدة جاهزة للمتابعة. استمر!',
                    'type' => 'progress',
                    'data' => ['user_index' => $idx + 1],
                    'read_at' => $idx % 3 === 0 ? now()->subDay() : null,
                    'deleted_at' => null,
                ]
            );
        }
    }
}

