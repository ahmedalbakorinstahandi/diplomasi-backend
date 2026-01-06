<?php

namespace App\Models\Scenarios;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserScenarioAttempt extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_scenario_attempts';

    /**
     * Indicates if the model should be timestamped.
     *
     * user_scenario_attempts table does NOT have created_at/updated_at columns.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'scenario_id',
        'status',
        'started_at',
        'finished_at',
        'description_read',
        'description_read_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'description_read' => 'boolean',
            'description_read_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the user scenario attempt.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\Users\User::class)->withTrashed();
    }

    /**
     * Get the scenario that owns the user scenario attempt.
     */
    public function scenario()
    {
        return $this->belongsTo(Scenario::class)->withTrashed();
    }

    /**
     * Get the user scenario question answers.
     */
    public function userScenarioQuestionAnswers()
    {
        return $this->hasMany(UserScenarioQuestionAnswer::class, 'attempt_id');
    }
}

