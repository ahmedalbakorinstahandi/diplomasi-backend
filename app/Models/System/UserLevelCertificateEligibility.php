<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLevelCertificateEligibility extends Model
{
    use HasFactory;

    protected $table = 'user_level_certificate_eligibilities';

    protected $fillable = [
        'user_id',
        'course_id',
        'level_id',
        'is_eligible',
        'first_eligible_at',
        'last_eligible_at',
        'last_evaluated_at',
        'artifact_status',
        'regeneration_reason',
        'generated_certificate_id',
    ];

    protected function casts(): array
    {
        return [
            'is_eligible' => 'boolean',
            'first_eligible_at' => 'datetime',
            'last_eligible_at' => 'datetime',
            'last_evaluated_at' => 'datetime',
        ];
    }

    public function getFinalStateAttribute(): string
    {
        if (!$this->is_eligible) {
            return 'not_eligible';
        }

        return match ($this->artifact_status) {
            'generated' => 'generated',
            'regeneration_needed' => 'eligible_regeneration_needed',
            default => 'eligible_not_generated',
        };
    }
}
