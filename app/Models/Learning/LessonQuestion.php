<?php

namespace App\Models\Learning;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonQuestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lesson_questions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'lesson_id',
        'type',
        'question_text',
        'attached_path',
        'explanation',
        'score',
        'order_index',
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
        ];
    }

    /**
     * Get the lesson that owns the lesson question.
     */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class)->withTrashed();
    }

    /**
     * Get the lesson question options.
     */
    public function lessonQuestionOptions()
    {
        return $this->hasMany(LessonQuestionOption::class, 'question_id');
    }

    /**
     * Get the user lesson question answers.
     */
    public function userLessonQuestionAnswers()
    {
        return $this->hasMany(\App\Models\Progress\UserLessonQuestionAnswer::class, 'question_id');
    }

    /**
     * Get the user lesson attempts that have this as current question.
     */
    public function currentUserLessonAttempts()
    {
        return $this->hasMany(\App\Models\Progress\UserLessonAttempt::class, 'current_question_id');
    }
}

