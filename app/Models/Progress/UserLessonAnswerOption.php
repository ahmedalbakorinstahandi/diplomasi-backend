<?php

namespace App\Models\Progress;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLessonAnswerOption extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_lesson_answer_options';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_answer_id',
        'option_id',
        'is_correct',
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
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the user lesson question answer that owns the user lesson answer option.
     */
    public function userLessonQuestionAnswer()
    {
        return $this->belongsTo(UserLessonQuestionAnswer::class, 'user_answer_id')->withTrashed();
    }

    /**
     * Get the lesson question option that owns the user lesson answer option.
     */
    public function lessonQuestionOption()
    {
        return $this->belongsTo(\App\Models\Learning\LessonQuestionOption::class, 'option_id')->withTrashed();
    }
}

