<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingEmailNotification extends Model
{
    use HasFactory;

    protected $table = 'billing_email_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'to_email',
        'subject',
        'content',
        'attachments',
        'payload',
        'send_at',
        'status',
        'attempts',
        'sent_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'payload' => 'array',
            'send_at' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}

