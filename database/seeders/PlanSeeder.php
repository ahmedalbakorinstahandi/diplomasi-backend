<?php

namespace Database\Seeders;

use App\Models\Billing\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $subscriptionFeatures = [
            'الوصول إلى كل المستويات',
            'الوصول إلى كل الدروس',
            'الوصول إلى كل السيناريوهات',
            'إصدار شهادات إتمام معتمدة باسمك من المنصة عند إتمام المستويات والكورسات',
        ];

        $tiers = [
            'Basic' => [
                'description' => 'اشتراك يفتح لك كل المستويات والدروس والسيناريوهات مع إصدار الشهادات الرسمية من المنصة.',
                'icon_url' => 'https://placehold.co/96x96/6366f1/white?text=Basic',
                'monthly' => 9.99,
                'semi_annual' => 53.94,   // ~8.99/شهر (خصم 10%)
                'annual' => 95.90,        // ~7.99/شهر (خصم 20%)
            ],
            'Pro' => [
                'description' => 'اشتراك يفتح لك كل المستويات والدروس والسيناريوهات مع إصدار الشهادات الرسمية من المنصة.',
                'icon_url' => 'https://placehold.co/96x96/0ea5e9/white?text=Pro',
                'monthly' => 19.99,
                'semi_annual' => 107.95,   // ~17.99/شهر (خصم 10%)
                'annual' => 191.90,        // ~15.99/شهر (خصم 20%)
            ],
            'Premium' => [
                'description' => 'اشتراك يفتح لك كل المستويات والدروس والسيناريوهات مع إصدار الشهادات الرسمية من المنصة.',
                'icon_url' => 'https://placehold.co/96x96/f59e0b/white?text=Premium',
                'monthly' => 34.99,
                'semi_annual' => 188.94,   // ~31.49/شهر (خصم 10%)
                'annual' => 335.90,        // ~27.99/شهر (خصم 20%)
            ],
        ];

        // php artisan db:seed --class=PlanSeeder

        foreach ($tiers as $name => $tier) {
            foreach (['monthly' => 'monthly', 'semi_annual' => 'semi_annual', 'annual' => 'annual'] as $intervalKey => $interval) {
                $price = $tier[$intervalKey];
                Plan::withTrashed()->updateOrCreate(
                    ['name' => $name, 'interval' => $interval],
                    [
                        'name' => $name,
                        'price' => $price,
                        'interval' => $interval,
                        'description' => $tier['description'],
                        'icon_url' => $tier['icon_url'],
                        'features' => $subscriptionFeatures,
                        'deleted_at' => null,
                    ]
                );
            }
        }
    }
}
