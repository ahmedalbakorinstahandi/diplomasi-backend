<?php

namespace App\Models\AiNegotiator;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiNegotiatorMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_negotiator_messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ai_negotiator_session_id',
        'role',
        'type',
        'content',
        'tokens_used',
        'state_at_time',
        'order_index',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tokens_used' => 'integer',
            'order_index' => 'integer',
        ];
    }

    /**
     * Get the session that owns the message.
     */
    public function session()
    {
        return $this->belongsTo(AiNegotiatorSession::class, 'ai_negotiator_session_id')->withTrashed();
    }
}
