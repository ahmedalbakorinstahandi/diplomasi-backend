<?php

namespace App\Models\Negotiation;

use Database\Factories\Negotiation\NegotiationSituationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NegotiationSituation extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): NegotiationSituationFactory
    {
        return NegotiationSituationFactory::new();
    }

    protected $table = 'negotiation_situations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'negotiation_level_id',
        'prompt_text',
        'prompt_context',
        'insight',
        'prompt_type',
        'order_index',
        'is_published',
        'is_free',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_free' => 'boolean',
        ];
    }

    /**
     * Get the negotiation level that owns the situation.
     */
    public function negotiationLevel()
    {
        return $this->belongsTo(NegotiationLevel::class)->withTrashed();
    }

    /**
     * Get the responses for the situation.
     */
    public function negotiationResponses()
    {
        return $this->hasMany(NegotiationResponse::class);
    }
}
