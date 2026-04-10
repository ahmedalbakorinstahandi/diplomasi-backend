<?php

namespace App\Models\Billing;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppleIapSubscriptionOwnership extends Model
{
    protected $table = 'apple_iap_subscription_ownerships';

    protected $fillable = [
        'user_id',
        'original_transaction_id',
        'plan_id',
        'product_id',
        'environment',
        'linked_at',
    ];

    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
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
}
