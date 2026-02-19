<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedPaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'saved_payment_methods';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'token',
        'status',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'is_default',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exp_month' => 'integer',
            'exp_year' => 'integer',
            'is_default' => 'boolean',
            'meta' => 'array',
        ];
    }
}

