<?php

namespace App\Models\AiNegotiator;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiNegotiatorSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_negotiator_sessions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'session_type',
        'session_state',
        'difficulty',
        'training_mode',
        'situation_type',
        'intake_data',
        'opponent_persona',
        'started_at',
        'simulating_started_at',
        'completed_at',
        'abandoned_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'intake_data' => 'array',
            'opponent_persona' => 'array',
            'started_at' => 'datetime',
            'simulating_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'abandoned_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the session.
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Get the messages for the session.
     */
    public function messages()
    {
        return $this->hasMany(AiNegotiatorMessage::class)->orderBy('order_index');
    }

    /**
     * Get the state transition events for the session.
     */
    public function events()
    {
        return $this->hasMany(AiNegotiatorSessionEvent::class)->orderBy('created_at');
    }

    /**
     * Get the evaluation for the session.
     */
    public function evaluation()
    {
        return $this->hasOne(AiNegotiatorEvaluation::class);
    }

    /**
     * Get credit transactions linked to this session.
     */
    public function creditTransactions()
    {
        return $this->hasMany(AiNegotiatorCreditTransaction::class);
    }
}
