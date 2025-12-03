<?php

namespace App\Models\Learning;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonSummary extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lesson_summaries';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'lesson_id',
        'content',
        'attached_path',
    ];

    /**
     * Get the lesson that owns the lesson summary.
     */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class)->withTrashed();
    }
}

