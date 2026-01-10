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
        'image_url',
        'template_path',
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

    /**
     * Scopes
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeForLevel($query, int $levelId)
    {
        return $query->where('level_id', $levelId);
    }

    public function scopeIssued($query)
    {
        return $query->whereNotNull('issued_at');
    }

    public function scopeNotRevoked($query)
    {
        // TODO: عندما نضيف status field، سنستخدمه هنا
        return $query;
    }

    /**
     * Accessors
     */
    public function getVerificationUrlAttribute(): string
    {
        return config('app.url') . '/api/v1/general/certificates/verify/' . $this->certificate_code;
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if (!$this->image_url) {
            return null;
        }
        return config('app.url') . '/api/v1/user/certificates/' . $this->id . '/download';
    }
}

