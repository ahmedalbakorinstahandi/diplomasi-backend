<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'subscription_events';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'subscription_id',
        'event_type',
        'plan_id',
        'status',
        'start_date',
        'end_date',
        'plan_price',
        'amount_charged',
        'amount_refunded',
        'currency',
        'stripe_invoice_id',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'stripe_event_id',
        'meta',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'plan_price' => 'decimal:2',
            'amount_charged' => 'decimal:2',
            'amount_refunded' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the subscription that owns the subscription event.
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class)->withTrashed();
    }

    /**
     * Get the plan.
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }
}

