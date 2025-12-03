<?php

namespace App\Models\Progress;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLessonQuestionAnswer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_lesson_question_answers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'attempt_id',
        'question_id',
        'step_index',
        'is_correct',
        'score',
        'time_spent',
        'answered_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'score' => 'decimal:2',
            'answered_at' => 'datetime',
        ];
    }

    /**
     * Get the user lesson attempt that owns the user lesson question answer.
     */
    public function userLessonAttempt()
    {
        return $this->belongsTo(UserLessonAttempt::class, 'attempt_id')->withTrashed();
    }

    /**
     * Get the lesson question that owns the user lesson question answer.
     */
    public function lessonQuestion()
    {
        return $this->belongsTo(\App\Models\Learning\LessonQuestion::class, 'question_id')->withTrashed();
    }

    /**
     * Get the user lesson answer options.
     */
    public function userLessonAnswerOptions()
    {
        return $this->hasMany(UserLessonAnswerOption::class, 'user_answer_id');
    }

    /**
     * Get the user lesson answer matches.
     */
    public function userLessonAnswerMatches()
    {
        return $this->hasMany(UserLessonAnswerMatch::class, 'user_answer_id');
    }
}

