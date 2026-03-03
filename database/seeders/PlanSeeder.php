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

        // أيقونة واحدة PNG (ليست SVG) لجميع الخطط
        $iconUrl = 'https://dummyimage.com/96x96/6366f1/ffffff.png';

        $plans = [
            [
                'name' => 'اشتراك المنصة',
                'interval' => 'monthly',
                'price' => 29.99,
            ],
            [
                'name' => 'اشتراك المنصة',
                'interval' => 'quarterly',
                'price' => 79.99,   // ~26.66/شهر (خصم ~11%)
            ],
            [
                'name' => 'اشتراك المنصة',
                'interval' => 'semi_annual',
                'price' => 149.99,   // ~25/شهر (خصم ~17%)
            ],
            [
                'name' => 'اشتراك المنصة',
                'interval' => 'annual',
                'price' => 279.99,   // ~23.33/شهر (خصم ~22%)
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
                    'icon_url' => $iconUrl,
                    'features' => $subscriptionFeatures,
                    'deleted_at' => null,
                ]
            );
        }
    }
}
