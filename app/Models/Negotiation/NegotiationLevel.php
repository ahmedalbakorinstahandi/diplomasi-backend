<?php

namespace App\Models\Negotiation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NegotiationLevel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'negotiation_levels';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'how_to_study',
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
            'is_published' => 'boolean',
        ];
    }

    /**
     * Get the situations for the negotiation level.
     */
    public function negotiationSituations()
    {
        return $this->hasMany(NegotiationSituation::class)->orderBy('order_index');
    }
}
