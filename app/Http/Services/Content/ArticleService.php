<?php

namespace App\Http\Services\Content;

use App\Http\Permissions\Content\ArticlePermission;
use App\Models\Content\Article;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;

class ArticleService
{
    public function index($filters = [])
    {
        $query = Article::query()->with([
            'author'
        ]);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'created_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = ['title', 'content'];
        $numericFields = [];
        $dateFields = ['created_at', 'published_at'];
        $exactMatchFields = ['is_published', 'author_id'];
        $inFields = [];

        $query = ArticlePermission::filterIndex($query);

        $query = FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );

        return $query;
    }

    public function show(int $id)
    {
        $article = Article::where('id', $id)->first();
        if (!$article) {
            MessageService::abort(404, 'messages.article.not_found');
        }

        $article->load([
            'author'
        ]);

        return $article;
    }

    public function create($data)
    {
        $article = Article::create($data);

        OrderHelper::assign($article);

        $article = $this->show($article->id);

        return $article;
    }

    public function update($data, $article)
    {
        $article->update($data);

        $article = $this->show($article->id);

        return $article;
    }

    public function delete($article)
    {
        $article->delete();
    }

    public function reorder($article, $validatedData)
    {
        OrderHelper::reorder($article, $validatedData['new_order_index'], 'order_index');

        return $article;
    }
}
