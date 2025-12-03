<?php

namespace App\Models\Scenarios;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserScenarioAnswerOption extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_scenario_answer_options';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_answer_id',
        'option_id',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the user scenario question answer that owns the user scenario answer option.
     */
    public function userScenarioQuestionAnswer()
    {
        return $this->belongsTo(UserScenarioQuestionAnswer::class, 'user_answer_id')->withTrashed();
    }

    /**
     * Get the scenario question option that owns the user scenario answer option.
     */
    public function scenarioQuestionOption()
    {
        return $this->belongsTo(ScenarioQuestionOption::class, 'option_id')->withTrashed();
    }
}

