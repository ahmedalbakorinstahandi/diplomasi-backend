<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Content\ArticlePermission;
use App\Http\Requests\Content\CreateArticleRequest;
use App\Http\Requests\Content\UpdateArticleRequest;
use App\Http\Resources\Content\ArticleResource;
use App\Http\Services\Content\ArticleService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use App\Http\Requests\Content\ReOrderArticleRequest;

class ArticleController extends Controller
{
    protected $articleService;

    public function __construct(ArticleService $articleService)
    {
        $this->articleService = $articleService;
    }

    public function index(Request $request, $message = null)
    {
        ArticlePermission::canView();

        $articles = $this->articleService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $articles,
            'meta' => true,
            'resource' => ArticleResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        ArticlePermission::canView();

        $article = $this->articleService->show($id);
        ArticlePermission::canShow($article);

        return ResponseService::response([
            'success' => true,
            'data' => $article,
            'resource' => ArticleResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateArticleRequest $request)
    {
        ArticlePermission::canCreate();

        $article = $this->articleService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $article,
            'message' => 'messages.article.created',
            'status' => 201,
            'resource' => ArticleResource::class,
        ]);
    }

    public function update(UpdateArticleRequest $request, int $id)
    {
        ArticlePermission::canUpdate();

        $article = $this->articleService->show($id);

        $article = $this->articleService->update($request->validated(), $article);

        return ResponseService::response([
            'success' => true,
            'data' => $article,
            'message' => 'messages.article.updated',
            'status' => 200,
            'resource' => ArticleResource::class,
        ]);
    }

    public function delete(int $id)
    {
        ArticlePermission::canDelete();

        $article = $this->articleService->show($id);

        $this->articleService->delete($article);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.article.deleted',
            'status' => 200,
        ]);
    }

    public function reorder(int $id, ReOrderArticleRequest $request)
    {
        ArticlePermission::canReorder();

        $article = $this->articleService->show($id);

        $article = $this->articleService->reorder($article, $request->validated());

        return $this->index(request(), 'messages.article.reordered');
    }
}
