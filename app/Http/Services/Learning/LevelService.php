<?php

namespace App\Http\Services\Learning;

use App\Http\Permissions\Learning\LevelPermission;
use App\Models\Learning\Level;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;

class LevelService
{
    public function index($filters = [])
    {
        $query = Level::query()->with([
            'course',
            'lessons',
            'scenarios',
            'levelTracks',
            'userLevelProgress',
            'certificates'
        ]);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'order_index';
        $filters['sort_order'] = $filters['sort_order'] ?? 'asc';

        $searchFields = ['title', 'description'];
        $numericFields = ['level_number', 'order_index'];
        $dateFields = ['created_at'];
        $exactMatchFields = ['is_published', 'is_free', 'has_certificate', 'course_id'];
        $inFields = [];

        $query = LevelPermission::filterIndex($query);

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
        $level = Level::where('id', $id)->first();
        if (!$level) {
            MessageService::abort(404, 'messages.level.not_found');
        }

        $level->load([
            'course',
            'lessons',
            'scenarios',
            'levelTracks',
            'userLevelProgress',
            'certificates'
        ]);

        return $level;
    }

    public function create($data)
    {
        $level = Level::create($data);

        $level = $this->show($level->id);

        return $level;
    }

    public function update($data, $level)
    {
        $level->update($data);

        $level = $this->show($level->id);

        return $level;
    }

    public function delete($level)
    {
        // Delete related records if needed
        $level->lessons()->delete();
        $level->scenarios()->delete();
        $level->levelTracks()->delete();
        $level->userLevelProgress()->delete();
        $level->certificates()->delete();

        $level->delete();
    }

    public function reorder($level, $validatedData)
    {
        // If order_index is used for ordering, update it
        if (isset($validatedData['order_index'])) {
            $level->order_index = $validatedData['order_index'];
            $level->save();
        }

        return $level;
    }
}

