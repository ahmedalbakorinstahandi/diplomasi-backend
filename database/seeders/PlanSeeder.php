<?php

namespace Database\Seeders;

use App\Models\Billing\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'stripe_plan_id' => 'plan_basic_monthly',
                'price' => 9.99,
                'interval' => 'monthly',
                'description' => 'خطة مناسبة للبدء والوصول للمحتوى المجاني وبعض المحتوى المدفوع.',
                'icon_url' => 'https://i.imgur.com/3G3DcqC.png',
            ],
            [
                'name' => 'Pro',
                'stripe_plan_id' => 'plan_pro_monthly',
                'price' => 19.99,
                'interval' => 'monthly',
                'description' => 'وصول كامل للمحتوى مع مزايا إضافية ودعم أسرع.',
                'icon_url' => 'https://i.imgur.com/9Qq5G2K.png',
            ],
            [
                'name' => 'Premium',
                'stripe_plan_id' => 'plan_premium_annual',
                'price' => 149.00,
                'interval' => 'annual',
                'description' => 'أفضل قيمة سنوية مع امتيازات كاملة وشهادات.',
                'icon_url' => 'https://i.imgur.com/Q9aZp0Q.png',
            ],
        ];

        foreach ($plans as $plan) {
            Plan::withTrashed()->updateOrCreate(
                ['stripe_plan_id' => $plan['stripe_plan_id']],
                [
                    'name' => $plan['name'],
                    'price' => $plan['price'],
                    'interval' => $plan['interval'],
                    'description' => $plan['description'],
                    'icon_url' => $plan['icon_url'],
                    'deleted_at' => null,
                ]
            );
        }
    }
}

