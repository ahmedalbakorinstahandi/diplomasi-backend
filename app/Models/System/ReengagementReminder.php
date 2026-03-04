<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class ReengagementReminder extends Model
{
    protected $table = 'reengagement_reminders';

    protected $fillable = [
        'amount',
        'unit',
        'title',
        'body',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public const UNITS = ['day', 'week', 'month', 'year'];
}
