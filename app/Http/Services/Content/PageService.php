<?php

namespace App\Http\Services\Content;

use App\Http\Permissions\Content\PagePermission;
use App\Models\Content\Page;
use App\Services\FilterService;
use App\Services\MessageService;

class PageService
{
    public function index($filters = [])
    {
        $query = Page::query();

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'created_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = ['title', 'slug', 'content'];
        $numericFields = [];
        $dateFields = ['created_at'];
        $exactMatchFields = ['is_published'];
        $inFields = [];

        $query = PagePermission::filterIndex($query);

        return FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );
    }

    public function show(int $id): Page
    {
        $page = Page::where('id', $id)->first();
        if (!$page) {
            MessageService::abort(404, 'messages.page.not_found');
        }

        return $page;
    }

    public function showBySlug(string $slug): Page
    {
        $page = Page::where('slug', $slug)->first();
        if (!$page) {
            MessageService::abort(404, 'messages.page.not_found');
        }

        PagePermission::canShow($page);

        return $page;
    }

    public function create(array $data): Page
    {
        $page = Page::create($data);

        return $this->show($page->id);
    }

    public function update(array $data, Page $page): Page
    {
        $page->update($data);

        return $this->show($page->id);
    }

    public function delete(Page $page): void
    {
        $page->delete();
    }
}
