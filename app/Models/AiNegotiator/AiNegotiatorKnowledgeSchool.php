<?php

namespace App\Models\AiNegotiator;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiNegotiatorKnowledgeSchool extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_negotiator_knowledge_schools';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'ethical_notes',
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
     * Get response library items that reference this school.
     */
    public function responseLibraryItems()
    {
        return $this->hasMany(AiNegotiatorResponseLibraryItem::class);
    }
}
