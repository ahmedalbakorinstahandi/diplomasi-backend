<?php

namespace App\Models\AiNegotiator;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiNegotiatorRubricItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_negotiator_rubric_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'title',
        'description',
        'weight',
        'order_index',
        'is_published',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'order_index' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Get evaluation scores that reference this rubric item.
     */
    public function evaluationScores()
    {
        return $this->hasMany(AiNegotiatorEvaluationScore::class);
    }
}
