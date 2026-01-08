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
                'features' => [
                    'الوصول إلى المحتوى المجاني',
                    'الوصول إلى 30% من المحتوى المدفوع',
                    'دعم فني عبر البريد الإلكتروني',
                    'تحديثات شهرية للمحتوى',
                ],
            ],
            [
                'name' => 'Pro',
                'stripe_plan_id' => 'plan_pro_monthly',
                'price' => 19.99,
                'interval' => 'monthly',
                'description' => 'وصول كامل للمحتوى مع مزايا إضافية ودعم أسرع.',
                'icon_url' => 'https://i.imgur.com/9Qq5G2K.png',
                'features' => [
                    'الوصول الكامل لجميع الدروس والسيناريوهات',
                    'دعم فني ذو أولوية',
                    'تحميل المحتوى للمشاهدة دون اتصال',
                    'شهادة إتمام للدورات',
                    'الوصول المبكر للمحتوى الجديد',
                ],
            ],
            [
                'name' => 'Premium',
                'stripe_plan_id' => 'plan_premium_annual',
                'price' => 149.00,
                'interval' => 'annual',
                'description' => 'أفضل قيمة سنوية مع امتيازات كاملة وشهادات.',
                'icon_url' => 'https://i.imgur.com/Q9aZp0Q.png',
                'features' => [
                    'جميع مميزات الباقة الاحترافية',
                    'خصم 38% على الاشتراك السنوي',
                    'جلسات استشارية شهرية مباشرة',
                    'شهادات معتمدة لجميع الدورات',
                    'الوصول مدى الحياة للمحتوى المضاف خلال فترة الاشتراك',
                    'أولوية في الدعم الفني على مدار الساعة',
                ],
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
                    'features' => $plan['features'],
                    'deleted_at' => null,
                ]
            );
        }
    }
}
