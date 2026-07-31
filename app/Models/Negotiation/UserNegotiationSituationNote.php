<?php

namespace App\Models\Negotiation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserNegotiationSituationNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_negotiation_situation_notes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'negotiation_situation_id',
        'note_text',
    ];

    /**
     * Get the user that owns the note.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\Users\User::class)->withTrashed();
    }

    /**
     * Get the negotiation situation that owns the note.
     */
    public function negotiationSituation()
    {
        return $this->belongsTo(NegotiationSituation::class)->withTrashed();
    }
}
