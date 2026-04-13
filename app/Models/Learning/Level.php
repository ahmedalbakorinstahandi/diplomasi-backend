<?php

namespace App\Models\Learning;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Level extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'levels';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'course_id',
        'level_number',
        'title',
        'description',
        'is_published',
        'is_free',
        'has_certificate',
        'certificate_template_path',
        'certificate_template_config',
        'order_index',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_free' => 'boolean',
            'has_certificate' => 'boolean',
            'certificate_template_config' => 'array',
        ];
    }

    /**
     * Get the course that owns the level.
     */
    public function course()
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    /**
     * Get the lessons for the level.
     */
    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * Get the scenarios for the level.
     */
    public function scenarios()
    {
        return $this->hasMany(\App\Models\Scenarios\Scenario::class);
    }

    /**
     * Get the level tracks.
     */
    public function levelTracks()
    {
        return $this->hasMany(LevelTrack::class)->orderBy('order_index');
    }

    /**
     * Get the user level progress.
     */
    public function userLevelProgress()
    {
        return $this->hasMany(\App\Models\Progress\UserLevelProgress::class);
    }

    /**
     * Get the certificates.
     */
    public function certificates()
    {
        return $this->hasMany(\App\Models\System\Certificate::class);
    }
}
