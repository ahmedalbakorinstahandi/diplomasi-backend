<?php

namespace App\Models\Scenarios;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScenarioQuestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'scenario_questions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'scenario_id',
        'code',
        'type',
        'question_text',
        'attached_path',
        'explanation',
        'order_index',
    ];

    /**
     * Get the scenario that owns the scenario question.
     */
    public function scenario()
    {
        return $this->belongsTo(Scenario::class)->withTrashed();
    }

    /**
     * Get the scenario question options.
     */
    public function scenarioQuestionOptions()
    {
        return $this->hasMany(ScenarioQuestionOption::class, 'question_id');
    }

    /**
     * Get the next questions (questions that have this as next_question_id).
     */
    public function previousQuestions()
    {
        return $this->hasMany(ScenarioQuestionOption::class, 'next_question_id');
    }

    /**
     * Get the user scenario question answers.
     */
    public function userScenarioQuestionAnswers()
    {
        return $this->hasMany(UserScenarioQuestionAnswer::class, 'question_id');
    }

    /**
     * Get the scenarios that start with this question.
     */
    public function startingScenarios()
    {
        return $this->hasMany(Scenario::class, 'start_question_id');
    }
}

