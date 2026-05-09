<?php

namespace App\Models\Content;

use App\Models\Learning\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Podcast extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'podcasts';

    protected $fillable = [
        'course_id',
        'title',
        'slug',
        'description',
        'cover_image',
        'audio_url',
        'audio_path',
        'duration_seconds',
        'is_published',
        'is_free',
        'requires_subscription',
        'allow_download',
        'order_index',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published'         => 'boolean',
            'is_free'              => 'boolean',
            'requires_subscription'=> 'boolean',
            'allow_download'       => 'boolean',
            'duration_seconds'     => 'integer',
            'order_index'          => 'integer',
            'published_at'         => 'datetime',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function userPodcastProgress()
    {
        return $this->hasMany(\App\Models\Progress\UserPodcastProgress::class);
    }

    public function userPodcastFavorites()
    {
        return $this->hasMany(\App\Models\Progress\UserPodcastFavorite::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeFree(Builder $query): Builder
    {
        return $query->where('is_free', true);
    }
}
