<?php

namespace App\Models\Billing;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    protected $table = 'payment_attempts';

    protected $fillable = [
        'user_id',
        'plan_id',
        'merchant_reference',
        'geidea_session_id',
        'geidea_order_id',
        'geidea_subscription_id',
        'checkout_url',
        'amount',
        'currency',
        'status',
        'failure_reason',
        'subscription_id',
        'verified_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
