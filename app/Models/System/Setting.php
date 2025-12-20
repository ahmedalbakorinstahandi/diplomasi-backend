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

    // Helper methods
    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'int' => (int) $setting->value,
            'float' => (float) $setting->value,
            'bool' => (bool) $setting->value,
            'json' => json_decode($setting->value, true),
            'datetime' => $setting->value ? now()->parse($setting->value) : null,
            default => $setting->value,
        };
    }

    public static function setValue(string $key, $value, string $type = 'text'): void
    {
        $processedValue = match ($type) {
            'json' => json_encode($value),
            'bool' => $value ? '1' : '0',
            'datetime' => $value ? now()->parse($value)->toDateTimeString() : null,
            default => (string) $value,
        };

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $processedValue,
                'type' => $type,
                'is_settings' => true,
            ]
        );
    }
}
