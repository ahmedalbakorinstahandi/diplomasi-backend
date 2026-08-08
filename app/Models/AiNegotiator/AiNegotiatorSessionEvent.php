<?php

namespace App\Models\AiNegotiator;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiNegotiatorSessionEvent extends Model
{
    use HasFactory;

    protected $table = 'ai_negotiator_session_events';

    /**
     * Append-only audit trail — no updated_at.
     */
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ai_negotiator_session_id',
        'from_state',
        'to_state',
        'context',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the session that owns the event.
     */
    public function session()
    {
        return $this->belongsTo(AiNegotiatorSession::class, 'ai_negotiator_session_id')->withTrashed();
    }
}
