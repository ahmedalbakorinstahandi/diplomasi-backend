<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $table = 'webhook_events';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'payload_id',
        'event_type',
        'event_created_at',
        'payment_id',
        'secret_token_valid',
        'payload',
        'processed_at',
        'processing_status',
        'processing_error',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'event_created_at' => 'datetime',
            'processed_at' => 'datetime',
            'secret_token_valid' => 'boolean',
        ];
    }
}
