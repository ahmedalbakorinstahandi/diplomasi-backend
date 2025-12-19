<?php

namespace App\Http\Services\System;

use App\Http\Permissions\System\NotificationPermission;
use App\Models\System\Notification;
use App\Models\Users\User;
use App\Services\FilterService;
use App\Services\MessageService;

class NotificationService
{
    public function index($filters = [])
    {
        $query = Notification::query()->with(['user']);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = 'created_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = ['title', 'body'];
        $numericFields = [];
        $dateFields = ['created_at', 'read_at'];
        $exactMatchFields = ['user_id', 'type'];
        $inFields = ['type'];

        $query = NotificationPermission::filterIndex($query);

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
        $notification = Notification::where('id', $id)->first();
        if (!$notification) {
            MessageService::abort(404, 'messages.notification.not_found');
        }

        $notification->load(['user']);

        return $notification;
    }

    public function create($data)
    {
        // If no user_id provided, it's a general notification
        if (!isset($data['user_id'])) {
            $data['user_id'] = null;
        }

        $notification = Notification::create($data);

        $notification = $this->show($notification->id);

        return $notification;
    }

    public function update($data, $notification)
    {
        $notification->update($data);

        $notification = $this->show($notification->id);

        return $notification;
    }

    public function delete($notification)
    {
        $notification->delete();
    }

    public function markAsRead($notification)
    {
        $notification->read_at = now();
        $notification->save();

        return $notification;
    }

    public function markAllAsRead($userId)
    {
        Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return true;
    }

    public function getUnreadCount($userId)
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }
}

