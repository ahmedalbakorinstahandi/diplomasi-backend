<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';

    protected $fillable = [
        'user_id',
        'subscription_id',
        'payment_transaction_id',
        'invoice_number',
        'status',
        'amount_minor',
        'currency',
        'issued_at',
        'due_at',
        'paid_at',
        'pdf_path',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function paymentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }
}

