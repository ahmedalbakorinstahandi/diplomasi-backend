<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'plans';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'price',
        'interval',
        'description',
        'caption',
        'is_featured',
        'features',
        'icon_url',
        'ios_price',
        'ios_currency',
        'ios_product_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'json',
            'price' => 'decimal:2',
            'ios_price' => 'decimal:2',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * Get the subscriptions for the plan.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the subscription events.
     */
    public function subscriptionEvents()
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    public function features(): Attribute
    {
        return Attribute::make(
            get: fn(?string $value) => $value ? json_decode($value, true) : null,
            set: fn($value) => $value ? json_encode($value) : null,
        );
    }

    /**
     * تسمية المدة للعرض (شهري، 3 أشهر، 6 أشهر، سنة، إلخ).
     */
    public static function intervalToLabel(string $interval): string
    {
        return match (strtolower($interval)) {
            'monthly' => 'شهري',
            'quarterly' => '3 أشهر',
            'semi_annual' => '6 أشهر',
            'annual' => 'سنة',
            default => $interval,
        };
    }

    public function getIntervalLabelAttribute(): string
    {
        return self::intervalToLabel((string) $this->interval);
    }
}
