<?php

namespace App\Models\Progress;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLessonAnswerMatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_lesson_answer_matches';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_answer_id',
        'left_option_id',
        'right_option_id',
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
     * Get the user lesson question answer that owns the user lesson answer match.
     */
    public function userLessonQuestionAnswer()
    {
        return $this->belongsTo(UserLessonQuestionAnswer::class, 'user_answer_id')->withTrashed();
    }

    /**
     * Get the left option.
     */
    public function leftOption()
    {
        return $this->belongsTo(\App\Models\Learning\LessonQuestionOption::class, 'left_option_id')->withTrashed();
    }

    /**
     * Get the right option.
     */
    public function rightOption()
    {
        return $this->belongsTo(\App\Models\Learning\LessonQuestionOption::class, 'right_option_id')->withTrashed();
    }
}

