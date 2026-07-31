<?php

namespace App\Models\Negotiation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserNegotiationFinalTestAttemptAnswer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_negotiation_final_test_attempt_answers';

    /**
     * Indicates if the model should be timestamped.
     *
     * user_negotiation_final_test_attempt_answers table does NOT have created_at/updated_at columns.
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
        'user_negotiation_final_test_attempt_id',
        'negotiation_situation_id',
        'asked_style',
        'selected_negotiation_response_id',
        'is_correct',
        'answered_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    /**
     * Get the attempt that owns the answer.
     */
    public function userNegotiationFinalTestAttempt()
    {
        return $this->belongsTo(UserNegotiationFinalTestAttempt::class, 'user_negotiation_final_test_attempt_id')->withTrashed();
    }

    /**
     * Get the negotiation situation for the answer.
     */
    public function negotiationSituation()
    {
        return $this->belongsTo(NegotiationSituation::class)->withTrashed();
    }

    /**
     * Get the selected negotiation response.
     */
    public function selectedResponse()
    {
        return $this->belongsTo(NegotiationResponse::class, 'selected_negotiation_response_id')->withTrashed();
    }
}
