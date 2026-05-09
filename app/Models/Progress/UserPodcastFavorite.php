<?php

namespace App\Models\Progress;

use App\Models\Content\Podcast;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserPodcastFavorite extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_podcast_favorites';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'podcast_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function podcast()
    {
        return $this->belongsTo(Podcast::class)->withTrashed();
    }
}
