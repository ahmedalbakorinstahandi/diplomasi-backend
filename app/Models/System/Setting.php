<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key_name',
        'value',
        'type',
        'is_settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_settings' => 'boolean',
        ];
    }

    // attribute to get value as json
    public function getValueAttribute()
    {
        $rawValue = $this->attributes['value'] ?? null;
        $type = $this->attributes['type'] ?? null;

        return match ($type) {
            'int' => $rawValue !== null ? (int) $rawValue : null,
            'float' => $rawValue !== null ? (float) $rawValue : null,
            'bool' => $rawValue !== null ? (bool) $rawValue : null,
            'json' => $rawValue !== null ? json_decode($rawValue, true) : null,
            'datetime' => $rawValue ? now()->parse($rawValue) : null,
            default => $rawValue,
        };
    }
}
