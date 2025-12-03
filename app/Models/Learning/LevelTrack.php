<?php

namespace App\Models\Learning;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LevelTrack extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'level_tracks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'level_id',
        'trackable_id',
        'trackable_type',
        'order_index',
    ];

    /**
     * Get the level that owns the level track.
     */
    public function level()
    {
        return $this->belongsTo(Level::class)->withTrashed();
    }

    /**
     * Get the parent trackable model (lesson or scenario).
     */
    public function trackable()
    {
        return $this->morphTo();
    }
}

