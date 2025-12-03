<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'certificates';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'course_id',
        'level_id',
        'certificate_code',
        'issued_at',
        'qr_code',
        'pdf_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the certificate.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\Users\User::class)->withTrashed();
    }

    /**
     * Get the course that owns the certificate.
     */
    public function course()
    {
        return $this->belongsTo(\App\Models\Learning\Course::class)->withTrashed();
    }

    /**
     * Get the level that owns the certificate.
     */
    public function level()
    {
        return $this->belongsTo(\App\Models\Learning\Level::class)->withTrashed();
    }
}

