<?php

namespace App\Models\Learning;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lessons';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'level_id',
        'lesson_number',
        'title',
        'description',
        'video_url',
        'content',
        'order_index',
        'is_published',
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
        ];
    }

    /**
     * Get the level that owns the lesson.
     */
    public function level()
    {
        return $this->belongsTo(Level::class)->withTrashed();
    }

    /**
     * Get the lesson questions.
     */
    public function lessonQuestions()
    {
        return $this->hasMany(LessonQuestion::class);
    }

    /**
     * Get the lesson summary.
     */
    public function lessonSummary()
    {
        return $this->hasOne(LessonSummary::class);
    }

    /**
     * Get the user lesson progress.
     */
    public function userLessonProgress()
    {
        return $this->hasMany(\App\Models\Progress\UserLessonProgress::class);
    }

    /**
     * Get the user lesson attempts.
     */
    public function userLessonAttempts()
    {
        return $this->hasMany(\App\Models\Progress\UserLessonAttempt::class);
    }

    /**
     * Get the level tracks.
     */
    public function levelTrack()
    {
        return $this->morphOne(LevelTrack::class, 'trackable');
    }
}
