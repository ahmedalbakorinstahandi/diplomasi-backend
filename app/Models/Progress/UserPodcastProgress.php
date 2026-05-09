<?php

namespace App\Models\Progress;

use App\Models\Content\Podcast;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserPodcastProgress extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_podcast_progress';

    protected $fillable = [
        'user_id',
        'podcast_id',
        'position_seconds',
        'duration_seconds',
        'progress_percentage',
        'is_completed',
        'last_played_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'position_seconds'   => 'integer',
            'duration_seconds'   => 'integer',
            'progress_percentage'=> 'decimal:2',
            'is_completed'       => 'boolean',
            'last_played_at'     => 'datetime',
            'completed_at'       => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function podcast()
    {
        return $this->belongsTo(Podcast::class)->withTrashed();
    }
}
