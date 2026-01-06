<?php

namespace App\Models\Progress;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLessonAttempt extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_lesson_attempts';

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
        'current_question_id',
        'started_at',
        'finished_at',
        'total_time',
        'video_watched',
        'video_watched_at',
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
            'finished_at' => 'datetime',
            'video_watched' => 'boolean',
            'video_watched_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the user lesson attempt.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\Users\User::class)->withTrashed();
    }

    /**
     * Get the lesson that owns the user lesson attempt.
     */
    public function lesson()
    {
        return $this->belongsTo(\App\Models\Learning\Lesson::class)->withTrashed();
    }

    /**
     * Get the current question.
     */
    public function currentQuestion()
    {
        return $this->belongsTo(\App\Models\Learning\LessonQuestion::class, 'current_question_id')->withTrashed();
    }

    /**
     * Get the user lesson question answers.
     */
    public function userLessonQuestionAnswers()
    {
        return $this->hasMany(UserLessonQuestionAnswer::class, 'attempt_id');
    }
}

