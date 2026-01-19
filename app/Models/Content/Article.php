<?php

namespace App\Models\Content;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'articles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'author_id',
        'is_published',
        'published_at',
        'order_index',
        'image_url',
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
            'published_at' => 'datetime',
            'order_index' => 'integer',
        ];
    }

    /**
     * Get the author (user) that owns the article.
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }
}
