<?php

namespace App\Http\Services\Content;

use App\Http\Permissions\Content\FaqPermission;
use App\Models\Content\Faq;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;

class FaqService
{
    public function index($filters = [])
    {
        $query = Faq::query();

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'order_index';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = ['question', 'answer'];
        $numericFields = [];
        $dateFields = ['created_at'];
        $exactMatchFields = [];
        $inFields = [];

        $query = FaqPermission::filterIndex($query);

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
        $faq = Faq::where('id', $id)->first();
        if (!$faq) {
            MessageService::abort(404, 'messages.faq.not_found');
        }

        return $faq;
    }

    public function create($data)
    {
        $faq = Faq::create($data);

        OrderHelper::assign($faq, 'order_index');

        $faq = $this->show($faq->id);

        return $faq;
    }

    public function update($data, $faq)
    {
        $faq->update($data);

        $faq = $this->show($faq->id);

        return $faq;
    }

    public function delete($faq)
    {
        // Delete related records if needed in the future

        $faq->delete();
    }

    public function reorder($faq, $validatedData)
    {
        // Reorder functionality if order_index is added to the table
        OrderHelper::reorder($faq, $validatedData['new_order_index'], 'order_index');

        return $this->show($faq->id);
    }
}
