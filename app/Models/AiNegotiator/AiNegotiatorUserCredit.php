<?php

namespace App\Models\AiNegotiator;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiNegotiatorUserCredit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_negotiator_user_credits';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'credit_balance',
        'consumed_this_cycle',
        'cycle_started_at',
        'cycle_ends_at',
        'last_refilled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_balance' => 'integer',
            'consumed_this_cycle' => 'integer',
            'cycle_started_at' => 'datetime',
            'cycle_ends_at' => 'datetime',
            'last_refilled_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the credit balance.
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
