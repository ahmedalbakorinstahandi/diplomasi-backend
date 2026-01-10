<?php

namespace App\Models\Progress;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserCourse extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_courses';

    /**
     * Indicates if the model should be timestamped.
     *
     * user_courses table does NOT have created_at/updated_at columns.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'course_id',
        'subscription_id',
        'status',
        'started_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the user course.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\Users\User::class)->withTrashed();
    }

    /**
     * Get the course that owns the user course.
     */
    public function course()
    {
        return $this->belongsTo(\App\Models\Learning\Course::class)->withTrashed();
    }

    /**
     * Get the subscription that owns the user course.
     */
    public function subscription()
    {
        return $this->belongsTo(\App\Models\Billing\Subscription::class)->withTrashed();
    }

    /**
     * Check if the course is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed' && $this->completed_at !== null;
    }

    /**
     * Check if user has a certificate for this course
     */
    public function hasCertificate(): bool
    {
        return \App\Models\System\Certificate::where('user_id', $this->user_id)
            ->where('course_id', $this->course_id)
            ->whereNull('level_id')
            ->exists();
    }

    /**
     * Get certificate for this course (if exists)
     */
    public function getCertificate(): ?\App\Models\System\Certificate
    {
        return \App\Models\System\Certificate::where('user_id', $this->user_id)
            ->where('course_id', $this->course_id)
            ->whereNull('level_id')
            ->first();
    }
}

