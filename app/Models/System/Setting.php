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
        return match ($this->type) {
            'int' => (int) $this->value,
            'float' => (float) $this->value,
            'bool' => (bool) $this->value,
            'json' => json_decode($this->value, true),
            'datetime' => $this->value ? now()->parse($this->value) : null,
            default => $this->value,
        };
    }
}
