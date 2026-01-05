<?php

namespace App\Http\Services\Learning;

use App\Http\Permissions\Learning\GlossaryTermPermission;
use App\Models\Learning\GlossaryTerm;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;

class GlossaryTermService
{
    public function index($filters = [])
    {
        $query = GlossaryTerm::query();

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'created_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = ['term', 'definition'];
        $numericFields = [];
        $dateFields = ['created_at'];
        $exactMatchFields = ['language'];
        $inFields = [];

        $query = GlossaryTermPermission::filterIndex($query);

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
        $glossaryTerm = GlossaryTerm::where('id', $id)->first();
        if (!$glossaryTerm) {
            MessageService::abort(404, 'messages.glossary_term.not_found');
        }

        return $glossaryTerm;
    }

    public function create($data)
    {
        $glossaryTerm = GlossaryTerm::create($data);

        $glossaryTerm = $this->show($glossaryTerm->id);

        return $glossaryTerm;
    }

    public function update($data, $glossaryTerm)
    {
        $glossaryTerm->update($data);

        $glossaryTerm = $this->show($glossaryTerm->id);

        return $glossaryTerm;
    }

    public function delete($glossaryTerm)
    {
        // Delete related records if needed in the future

        $glossaryTerm->delete();
    }

    public function reorder($glossaryTerm, $validatedData)
    {
        // Reorder functionality if order_index is added to the table
        // OrderHelper::reorder($glossaryTerm, $validatedData['new_order_index'], 'order_index');

        return $this->show($glossaryTerm->id);
    }
}

