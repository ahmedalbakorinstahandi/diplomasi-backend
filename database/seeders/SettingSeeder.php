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
            ['key_name' => 'support.email', 'value' => 'support@diplomasi.app', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'support.phone', 'value' => '+962790000000', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'app.version', 'value' => '1.0.0', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'app.min_version', 'value' => '1.0.1', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'app.suggested_min_version', 'value' => '1.0.0', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'app.google_play_link', 'value' => 'https://play.google.com/store/apps/details?id=com.diplomasi.app', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'app.apple_store_link', 'value' => 'https://apps.apple.com/app/diplomasi/id000000000', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'social.facebook', 'value' => 'https://facebook.com/diplomasi', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'social.instagram', 'value' => 'https://instagram.com/diplomasi', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'social.twitter', 'value' => 'https://twitter.com/diplomasi', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'social.linkedin', 'value' => 'https://linkedin.com/company/diplomasi', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'social.youtube', 'value' => 'https://youtube.com/@diplomasi', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'site.tagline', 'value' => 'منصة تعليمية للدبلوماسية والتفاوض', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'learning.max_attempts', 'value' => '3', 'type' => 'int', 'is_settings' => true],
            ['key_name' => 'billing.moyasar.mode', 'value' => 'live', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'ui.home_banner', 'value' => json_encode(['title' => 'ابدأ رحلتك', 'subtitle' => 'تعلم بذكاء']), 'type' => 'json', 'is_settings' => true],
            ['key_name' => 'legal.terms_conditions', 'value' => '<p>نص تجريبي للشروط والأحكام.</p>', 'type' => 'html', 'is_settings' => true],

            // AI Negotiator
            ['key_name' => 'ai_negotiator.access_mode', 'value' => 'credits_based', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'ai_negotiator.free_credits_monthly', 'value' => '3', 'type' => 'int', 'is_settings' => true],
            ['key_name' => 'ai_negotiator.paid_credits_monthly', 'value' => '30', 'type' => 'int', 'is_settings' => true],
            ['key_name' => 'ai_negotiator.credit_reset_cycle', 'value' => 'monthly', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'ai_negotiator.llm_provider', 'value' => 'claude', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'ai_negotiator.llm_model', 'value' => 'claude-sonnet-4-6', 'type' => 'text', 'is_settings' => true],
            ['key_name' => 'ai_negotiator.max_messages_per_session', 'value' => '40', 'type' => 'int', 'is_settings' => true],
            ['key_name' => 'ai_negotiator.max_tokens_per_session', 'value' => '100000', 'type' => 'int', 'is_settings' => true],
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
