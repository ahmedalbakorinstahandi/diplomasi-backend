<?php

namespace App\Models\AiNegotiator;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiNegotiatorResponseLibraryItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_negotiator_response_library';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ai_negotiator_knowledge_school_id',
        'name',
        'response_text',
        'tone',
        'situation_type',
        'objection_type',
        'category',
        'when_to_use',
        'when_not_to_use',
        'risk',
        'difficulty',
        'example',
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
            'order_index' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Get the reference school for this library item.
     */
    public function knowledgeSchool()
    {
        return $this->belongsTo(AiNegotiatorKnowledgeSchool::class, 'ai_negotiator_knowledge_school_id')
            ->withTrashed();
    }
}
