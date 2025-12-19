<?php

namespace App\Models\Progress;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLevelProgress extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_level_progress';

    /**
     * Indicates if the model should be timestamped.
     *
     * user_level_progress table does NOT have created_at/updated_at columns.
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
        'level_id',
        'current_lesson_id',
        'status',
        'score',
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
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the user level progress.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\Users\User::class)->withTrashed();
    }

    /**
     * Get the level that owns the user level progress.
     */
    public function level()
    {
        return $this->belongsTo(\App\Models\Learning\Level::class)->withTrashed();
    }

    /**
     * Get the current lesson.
     */
    public function currentLesson()
    {
        return $this->belongsTo(\App\Models\Learning\Lesson::class, 'current_lesson_id')->withTrashed();
    }
}

