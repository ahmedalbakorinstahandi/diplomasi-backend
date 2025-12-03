<?php

namespace App\Models\Scenarios;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserScenarioQuestionAnswer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_scenario_question_answers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'question_id',
        'attempt_id',
        'step_index',
        'answered_at',
        'time_spent',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
        ];
    }

    /**
     * Get the scenario question that owns the user scenario question answer.
     */
    public function scenarioQuestion()
    {
        return $this->belongsTo(ScenarioQuestion::class, 'question_id')->withTrashed();
    }

    /**
     * Get the user scenario attempt that owns the user scenario question answer.
     */
    public function userScenarioAttempt()
    {
        return $this->belongsTo(UserScenarioAttempt::class, 'attempt_id')->withTrashed();
    }

    /**
     * Get the user scenario answer options.
     */
    public function userScenarioAnswerOptions()
    {
        return $this->hasMany(UserScenarioAnswerOption::class, 'user_answer_id');
    }
}

