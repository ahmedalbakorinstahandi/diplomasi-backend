<?php

namespace App\Http\Services\System;

use App\Http\Permissions\System\ReengagementReminderPermission;
use App\Models\System\ReengagementReminder;
use App\Services\FilterService;
use App\Services\MessageService;

class ReengagementReminderService
{
    public function index(array $filters = [])
    {
        $query = ReengagementReminder::query();

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'sort_order';
        $filters['sort_order'] = $filters['sort_order'] ?? 'asc';

        $searchFields = ['title', 'body'];
        $numericFields = ['amount', 'sort_order'];
        $dateFields = ['created_at', 'updated_at'];
        $exactMatchFields = ['unit', 'is_active'];
        $inFields = ['unit', 'is_active'];

        $query = ReengagementReminderPermission::filterIndex($query);

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

    public function show(int $id): ReengagementReminder
    {
        $reminder = ReengagementReminder::query()->where('id', $id)->first();
        if (!$reminder) {
            MessageService::abort(404, 'messages.reengagement_reminder.not_found');
        }
        return $reminder;
    }

    public function create(array $data): ReengagementReminder
    {
        $reminder = ReengagementReminder::query()->create($data);
        return $this->show($reminder->id);
    }

    public function update(array $data, ReengagementReminder $reminder): ReengagementReminder
    {
        $reminder->update($data);
        return $this->show($reminder->id);
    }

    public function delete(ReengagementReminder $reminder): void
    {
        $reminder->delete();
    }
}
