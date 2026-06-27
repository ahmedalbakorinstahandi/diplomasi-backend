<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Content\PagePermission;
use App\Http\Requests\Content\CreatePageRequest;
use App\Http\Requests\Content\UpdatePageRequest;
use App\Http\Resources\Content\PageResource;
use App\Http\Services\Content\PageService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct(
        protected PageService $pageService
    ) {}

    public function index(Request $request, $message = null)
    {
        PagePermission::canView();

        $pages = $this->pageService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $pages,
            'meta' => true,
            'resource' => PageResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        PagePermission::canView();

        $page = $this->pageService->show($id);
        PagePermission::canShow($page);

        return ResponseService::response([
            'success' => true,
            'data' => $page,
            'resource' => PageResource::class,
            'status' => 200,
        ]);
    }

    public function showBySlug(string $slug)
    {
        $page = $this->pageService->showBySlug($slug);

        return ResponseService::response([
            'success' => true,
            'data' => $page,
            'resource' => PageResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreatePageRequest $request)
    {
        PagePermission::canCreate();

        $page = $this->pageService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $page,
            'message' => 'messages.page.created',
            'status' => 201,
            'resource' => PageResource::class,
        ]);
    }

    public function update(UpdatePageRequest $request, int $id)
    {
        PagePermission::canUpdate();

        $page = $this->pageService->show($id);
        $page = $this->pageService->update($request->validated(), $page);

        return ResponseService::response([
            'success' => true,
            'data' => $page,
            'message' => 'messages.page.updated',
            'status' => 200,
            'resource' => PageResource::class,
        ]);
    }

    public function delete(int $id)
    {
        PagePermission::canDelete();

        $page = $this->pageService->show($id);
        $this->pageService->delete($page);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.page.deleted',
            'status' => 200,
        ]);
    }
}
