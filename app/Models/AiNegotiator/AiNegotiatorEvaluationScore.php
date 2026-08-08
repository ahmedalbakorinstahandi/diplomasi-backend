<?php

namespace App\Models\AiNegotiator;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiNegotiatorEvaluationScore extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_negotiator_evaluation_scores';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ai_negotiator_evaluation_id',
        'ai_negotiator_rubric_item_id',
        'score',
        'max_score',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'max_score' => 'integer',
        ];
    }

    /**
     * Get the evaluation that owns the score.
     */
    public function evaluation()
    {
        return $this->belongsTo(AiNegotiatorEvaluation::class, 'ai_negotiator_evaluation_id')->withTrashed();
    }

    /**
     * Get the rubric item for this score.
     */
    public function rubricItem()
    {
        return $this->belongsTo(AiNegotiatorRubricItem::class, 'ai_negotiator_rubric_item_id')->withTrashed();
    }
}
