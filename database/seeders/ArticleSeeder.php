<?php

namespace Database\Seeders;

use App\Models\Content\Article;
use App\Models\Users\User;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->where('email', 'admin@demo.test')->first()
            ?: User::query()->where('email', 'superadmin@demo.test')->first();

        for ($i = 1; $i <= 12; $i++) {
            $slug = "article-{$i}";
            $isPublished = $i % 4 !== 0;

            Article::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => "مقال رقم {$i}",
                    'content' => "هذا مقال تجريبي رقم {$i}. يحتوي على محتوى توعوي/تعليمي قابل للتبديل حسب الحاجة.",
                    'author_id' => $author?->id,
                    'is_published' => $isPublished,
                    'published_at' => $isPublished ? now()->subDays(30 - $i) : null,
                    'deleted_at' => null,
                ]
            );
        }
    }
}

