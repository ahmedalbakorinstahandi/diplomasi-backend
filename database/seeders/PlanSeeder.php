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

        $description = 'اشتراك يفتح لك كل المستويات والدروس والسيناريوهات مع إصدار الشهادات الرسمية من المنصة.';

        $plans = [
            [
                'name' => 'Basic',
                'interval' => 'monthly',
                'price' => 29.99,
                'icon_url' => 'https://dummyimage.com/96x96/6366f1/ffffff&text=Basic',
            ],
            [
                'name' => 'Premium',
                'interval' => 'quarterly',
                'price' => 79.99,   // ~26.66/شهر (خصم ~11%)
                'icon_url' => 'https://dummyimage.com/96x96/0ea5e9/ffffff&text=Premium',
            ],
            [
                'name' => 'Pro',
                'interval' => 'semi_annual',
                'price' => 149.99,   // ~25/شهر (خصم ~17%)
                'icon_url' => 'https://dummyimage.com/96x96/f59e0b/1f2937&text=Pro',
            ],
            [
                'name' => 'Master',
                'interval' => 'annual',
                'price' => 279.99,   // ~23.33/شهر (خصم ~22%)
                'icon_url' => 'https://dummyimage.com/96x96/8b5cf6/ffffff&text=Master',
            ],
        ];

        foreach ($plans as $plan) {
            Plan::withTrashed()->updateOrCreate(
                ['name' => $plan['name'], 'interval' => $plan['interval']],
                [
                    'name' => $plan['name'],
                    'price' => $plan['price'],
                    'interval' => $plan['interval'],
                    'description' => $description,
                    'icon_url' => $plan['icon_url'],
                    'features' => $subscriptionFeatures,
                    'deleted_at' => null,
                ]
            );
        }
    }
}
