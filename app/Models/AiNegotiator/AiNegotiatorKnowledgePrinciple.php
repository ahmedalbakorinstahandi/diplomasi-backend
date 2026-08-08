<?php

namespace App\Models\AiNegotiator;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiNegotiatorKnowledgePrinciple extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_negotiator_knowledge_principles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'when_to_use',
        'when_not_to_use',
        'example',
        'difficulty',
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
}
