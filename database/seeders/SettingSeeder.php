<?php

namespace Database\Seeders;

use App\Models\System\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key_name' => 'app.name', 'value' => 'Diplomasi', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'support.email', 'value' => 'support@demo.test', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'support.phone', 'value' => '+201000000999', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'app.version', 'value' => '1.0.0', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'app.min_version', 'value' => '1.0.1', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'app.suggested_min_version', 'value' => '1.0.0', 'type' => 'text', 'is_settings' => true],
            // ['key_name' => 'app.google_play_link', 'value' => 'https://play.google.com/store/apps/details?id=com.example.diplomasi', 'type' => 'text', 'is_settings' => true],
            // ['key_name' => 'app.apple_store_link', 'value' => 'https://apps.apple.com/app/diplomasi/id123456789', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'learning.max_attempts', 'value' => '3', 'type' => 'int', 'is_settings' => true],
            ['key_name' => 'ui.home_banner', 'value' => json_encode(['title' => 'ابدأ رحلتك', 'subtitle' => 'تعلم بذكاء']), 'type' => 'json', 'is_settings' => true],
            ['key_name' => 'legal.terms_conditions', 'value' => '<p>نص تجريبي للشروط والأحكام.</p>', 'type' => 'html', 'is_settings' => true],
        ];

        foreach ($settings as $s) {
            Setting::withTrashed()->updateOrCreate(
                ['key_name' => $s['key_name']],
                [
                    'value' => $s['value'],
                    'type' => $s['type'],
                    'is_settings' => $s['is_settings'],
                    'deleted_at' => null,
                ]
            );
        }
    }
}

