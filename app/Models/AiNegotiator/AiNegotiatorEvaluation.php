<?php

namespace App\Models\AiNegotiator;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiNegotiatorEvaluation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_negotiator_evaluations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ai_negotiator_session_id',
        'overall_score',
        'summary',
        'best_line',
        'weakest_line',
        'biggest_mistake',
        'quick_concession',
        'sensitive_info_leaked',
        'good_questions',
        'suggested_alternative_response',
        'retry_exercise',
        'suggested_next_difficulty',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overall_score' => 'integer',
            'quick_concession' => 'boolean',
            'sensitive_info_leaked' => 'boolean',
            'good_questions' => 'boolean',
        ];
    }

    /**
     * Get the session that owns the evaluation.
     */
    public function session()
    {
        return $this->belongsTo(AiNegotiatorSession::class, 'ai_negotiator_session_id')->withTrashed();
    }

    /**
     * Get the per-rubric scores for the evaluation.
     */
    public function scores()
    {
        return $this->hasMany(AiNegotiatorEvaluationScore::class);
    }
}
