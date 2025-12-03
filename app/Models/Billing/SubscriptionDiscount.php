<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionDiscount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'subscription_discounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'subscription_id',
        'discount_id',
        'discount_type',
        'discount_value',
        'applied_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the subscription that owns the subscription discount.
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class)->withTrashed();
    }

    /**
     * Get the discount coupon.
     */
    public function discountCoupon()
    {
        return $this->belongsTo(DiscountCoupon::class, 'discount_id')->withTrashed();
    }
}

