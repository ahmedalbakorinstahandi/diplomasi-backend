<?php

namespace App\Models\Negotiation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserNegotiationFinalTestAttempt extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_negotiation_final_test_attempts';

    /**
     * Indicates if the model should be timestamped.
     *
     * user_negotiation_final_test_attempts table does NOT have created_at/updated_at columns.
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
        'negotiation_level_id',
        'status',
        'score',
        'total_questions',
        'correct_count',
        'seed',
        'started_at',
        'finished_at',
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
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the attempt.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\Users\User::class)->withTrashed();
    }

    /**
     * Get the negotiation level that owns the attempt.
     */
    public function negotiationLevel()
    {
        return $this->belongsTo(NegotiationLevel::class)->withTrashed();
    }

    /**
     * Get the answers for the attempt.
     */
    public function answers()
    {
        return $this->hasMany(UserNegotiationFinalTestAttemptAnswer::class, 'user_negotiation_final_test_attempt_id');
    }
}
