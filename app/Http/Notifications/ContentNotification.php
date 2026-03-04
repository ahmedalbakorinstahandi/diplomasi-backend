<?php

namespace App\Http\Notifications;

use App\Http\Services\System\NotificationService;
use App\Models\Content\Article;

class ContentNotification
{
    public static function articlePublished(Article $article): void
    {
        app(NotificationService::class)->sendToAll(
            title: 'مقال جديد ينتظرك 📰',
            body: 'نشرنا مقالًا جديدًا بعنوان "' . $article->title . '". افتحه الآن واكتشف أفكارًا جديدة.',
            type: 'article_published',
            data: [
                'article_id' => (int) $article->id,
                'screen' => 'articles',
            ]
        );
    }
}
