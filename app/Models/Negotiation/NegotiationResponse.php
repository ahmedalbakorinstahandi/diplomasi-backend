<?php

namespace App\Models\Negotiation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NegotiationResponse extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'negotiation_responses';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'negotiation_situation_id',
        'style',
        'response_text',
        'explanation',
    ];

    /**
     * Get the negotiation situation that owns the response.
     */
    public function negotiationSituation()
    {
        return $this->belongsTo(NegotiationSituation::class)->withTrashed();
    }
}
