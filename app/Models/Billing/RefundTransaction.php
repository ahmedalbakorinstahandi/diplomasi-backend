<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundTransaction extends Model
{
    use HasFactory;

    protected $table = 'refund_transactions';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'payment_transaction_id',
        'provider',
        'provider_payment_id',
        'provider_refund_id',
        'amount_minor',
        'currency',
        'status',
        'gateway_status',
        'error_code',
        'error_message',
        'raw_response',
        'requested_at',
        'refunded_at',
        'failed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'raw_response' => 'array',
            'requested_at' => 'datetime',
            'refunded_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function paymentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }
}
