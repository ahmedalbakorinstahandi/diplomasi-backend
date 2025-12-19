<?php

namespace Database\Seeders;

use App\Models\Billing\DiscountCoupon;
use Illuminate\Database\Seeder;

class DiscountCouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'WELCOME10',
                'description' => 'خصم ترحيبي 10%',
                'percentage' => 10,
                'max_uses' => 500,
                'max_uses_by_user' => 1,
                'used_count' => 0,
                'expires_at' => now()->addMonths(6),
                'discount_type' => 'percentage',
                'discount_value' => 10,
            ],
            [
                'code' => 'SAVE5',
                'description' => 'خصم ثابت 5 USD',
                'percentage' => 0,
                'max_uses' => 300,
                'max_uses_by_user' => 2,
                'used_count' => 0,
                'expires_at' => now()->addMonths(3),
                'discount_type' => 'fixed',
                'discount_value' => 5,
            ],
            [
                'code' => 'ANNUAL20',
                'description' => 'خصم 20% على الخطط السنوية',
                'percentage' => 20,
                'max_uses' => 200,
                'max_uses_by_user' => 1,
                'used_count' => 0,
                'expires_at' => now()->addMonths(12),
                'discount_type' => 'percentage',
                'discount_value' => 20,
            ],
        ];

        foreach ($coupons as $coupon) {
            DiscountCoupon::withTrashed()->updateOrCreate(
                ['code' => $coupon['code']],
                $coupon + ['deleted_at' => null]
            );
        }
    }
}

