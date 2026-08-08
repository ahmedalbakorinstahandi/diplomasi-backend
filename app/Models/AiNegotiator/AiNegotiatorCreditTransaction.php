<?php

namespace App\Models\AiNegotiator;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiNegotiatorCreditTransaction extends Model
{
    use HasFactory;

    protected $table = 'ai_negotiator_credit_transactions';

    /**
     * Append-only ledger — no updated_at.
     */
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'ai_negotiator_session_id',
        'type',
        'amount',
        'balance_after',
        'meta',
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
            'amount' => 'integer',
            'balance_after' => 'integer',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Get the session linked to a consume transaction (nullable).
     */
    public function session()
    {
        return $this->belongsTo(AiNegotiatorSession::class, 'ai_negotiator_session_id')->withTrashed();
    }
}
