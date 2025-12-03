<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscountCoupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'discount_coupons';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'description',
        'percentage',
        'max_uses',
        'max_uses_by_user',
        'used_count',
        'expires_at',
        'discount_type',
        'discount_value',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'discount_value' => 'decimal:2',
        ];
    }

    /**
     * Get the subscription discounts.
     */
    public function subscriptionDiscounts()
    {
        return $this->hasMany(SubscriptionDiscount::class, 'discount_id');
    }
}

