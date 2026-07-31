<?php

namespace App\Models\Negotiation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserNegotiationSituationProgress extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_negotiation_situation_progress';

    /**
     * Indicates if the model should be timestamped.
     *
     * user_negotiation_situation_progress table does NOT have created_at/updated_at columns.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'negotiation_situation_id',
        'status',
        'track_status',
        'is_completed',
        'score',
        'started_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'is_completed' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the progress.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\Users\User::class)->withTrashed();
    }

    /**
     * Get the negotiation situation that owns the progress.
     */
    public function negotiationSituation()
    {
        return $this->belongsTo(NegotiationSituation::class)->withTrashed();
    }
}
