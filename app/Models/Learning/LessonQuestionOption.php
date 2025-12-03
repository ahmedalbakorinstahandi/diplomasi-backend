<?php

namespace App\Models\Learning;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonQuestionOption extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lesson_question_options';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'question_id',
        'option_text',
        'pair_key',
        'is_correct',
        'attached_path',
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
            'is_correct' => 'boolean',
        ];
    }

    /**
     * Get the lesson question that owns the option.
     */
    public function lessonQuestion()
    {
        return $this->belongsTo(LessonQuestion::class, 'question_id')->withTrashed();
    }

    /**
     * Get the user lesson answer options.
     */
    public function userLessonAnswerOptions()
    {
        return $this->hasMany(\App\Models\Progress\UserLessonAnswerOption::class, 'option_id');
    }

    /**
     * Get the user lesson answer matches as left option.
     */
    public function leftUserLessonAnswerMatches()
    {
        return $this->hasMany(\App\Models\Progress\UserLessonAnswerMatch::class, 'left_option_id');
    }

    /**
     * Get the user lesson answer matches as right option.
     */
    public function rightUserLessonAnswerMatches()
    {
        return $this->hasMany(\App\Models\Progress\UserLessonAnswerMatch::class, 'right_option_id');
    }
}

