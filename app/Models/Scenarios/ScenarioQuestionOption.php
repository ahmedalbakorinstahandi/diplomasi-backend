<?php

namespace App\Models\Scenarios;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScenarioQuestionOption extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'scenario_question_options';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'question_id',
        'option_text',
        'feedback_text',
        'next_question_id',
        'attached_path',
        'order_index',
    ];

    /**
     * Get the scenario question that owns the option.
     */
    public function scenarioQuestion()
    {
        return $this->belongsTo(ScenarioQuestion::class, 'question_id')->withTrashed();
    }

    /**
     * Get the next question.
     */
    public function nextQuestion()
    {
        return $this->belongsTo(ScenarioQuestion::class, 'next_question_id')->withTrashed();
    }

    /**
     * Get the user scenario answer options.
     */
    public function userScenarioAnswerOptions()
    {
        return $this->hasMany(UserScenarioAnswerOption::class, 'option_id');
    }
}

