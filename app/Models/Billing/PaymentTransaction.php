<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $table = 'payment_transactions';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'plan_id',
        'subscription_id',
        'merchant_reference_id',
        'given_id',
        'provider',
        'provider_payment_id',
        'original_transaction_id',
        'amount_minor',
        'currency',
        'attempt_no',
        'billing_period_start',
        'billing_period_end',
        'next_retry_at',
        'status',
        'gateway_status',
        'redirect_url',
        'finalized_at',
        'verified_at',
        'last_error_code',
        'last_error_message',
        'raw_response',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'attempt_no' => 'integer',
            'billing_period_start' => 'datetime',
            'billing_period_end' => 'datetime',
            'next_retry_at' => 'datetime',
            'raw_response' => 'array',
            'finalized_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'payment_transaction_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function refunds()
    {
        return $this->hasMany(RefundTransaction::class, 'payment_transaction_id');
    }
}

