<?php

namespace App\Models\Scenarios;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Scenario extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'scenarios';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'level_id',
        'title',
        'description',
        'is_published',
        'is_free',
        'start_question_id',
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
            'description' => 'array',
            'is_published' => 'boolean',
            'is_free' => 'boolean',
        ];
    }

    /**
     * Get the level that owns the scenario.
     */
    public function level()
    {
        return $this->belongsTo(\App\Models\Learning\Level::class)->withTrashed();
    }

    /**
     * Get the start question.
     */
    public function startQuestion()
    {
        return $this->belongsTo(ScenarioQuestion::class, 'start_question_id')->withTrashed();
    }

    /**
     * Get the scenario questions.
     */
    public function scenarioQuestions()
    {
        return $this->hasMany(ScenarioQuestion::class);
    }

    /**
     * Get the user scenario attempts.
     */
    public function userScenarioAttempts()
    {
        return $this->hasMany(UserScenarioAttempt::class);
    }

    /**
     * Get the level tracks.
     */
    public function levelTracks()
    {
        return $this->morphMany(\App\Models\Learning\LevelTrack::class, 'trackable');
    }
}

