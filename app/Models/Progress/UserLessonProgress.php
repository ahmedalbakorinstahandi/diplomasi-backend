<?php

namespace App\Models\Progress;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLessonProgress extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_lesson_progress';

    /**
     * Indicates if the model should be timestamped.
     *
     * user_lesson_progress table does NOT have created_at/updated_at columns.
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
        'lesson_id',
        'status',
        'score',
        'progress_percentage',
        'track_status',
        'is_completed',
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
            'score' => 'decimal:2',
            'progress_percentage' => 'decimal:2',
            'is_completed' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the user lesson progress.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\Users\User::class)->withTrashed();
    }

    /**
     * Get the lesson that owns the user lesson progress.
     */
    public function lesson()
    {
        return $this->belongsTo(\App\Models\Learning\Lesson::class)->withTrashed();
    }
}

