<?php

namespace App\Http\Services\System;

use App\Http\Permissions\System\NotificationPermission;
use App\Models\System\Notification;
use App\Models\Users\User;
use App\Services\FirebaseService;
use App\Services\FilterService;
use App\Services\MessageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    public static function storeNotification(
        array $users_ids,
        array $notificationable,
        string $title,
        string $body,
        array $replace = [],
        array $data = [],
        bool $isCustom = false
    ): void {
        $notificationService = app(self::class);

        foreach (array_values(array_unique(array_map('intval', $users_ids))) as $userId) {
            if ($userId <= 0) {
                continue;
            }

            $notificationService->create([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'type' => $notificationable['type'] ?? 'custom',
                'data' => array_filter([
                    ...$data,
                    'notificationable_id' => $notificationable['id'] ?? null,
                    'notificationable_type' => $notificationable['type'] ?? 'custom',
                ], static fn($value) => $value !== null),
            ]);
        }
    }

    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        string $type,
        array $data = []
    ): Notification {
        $notification = $this->create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'data' => $data,
        ]);

        FirebaseService::sendToTokensAndStorage(
            users_ids: [$userId],
            notificationable: ['id' => $notification->id, 'type' => $type],
            title: $title,
            body: $body,
            replace: [],
            data: $data,
            isCustom: true,
            channelId: null,
            storeInDatabase: false
        );

        return $notification;
    }

    public function sendToUsers(
        array $userIds,
        string $title,
        string $body,
        string $type,
        array $data = []
    ): Collection {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($userIds)) {
            return collect();
        }

        $notificationIds = [];
        foreach ($userIds as $userId) {
            $notification = $this->create([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'data' => $data,
            ]);
            $notificationIds[] = $notification->id;
        }

        FirebaseService::sendToTokensAndStorage(
            users_ids: $userIds,
            notificationable: ['id' => null, 'type' => $type],
            title: $title,
            body: $body,
            replace: [],
            data: $data,
            isCustom: true,
            channelId: null,
            storeInDatabase: false
        );

        return Notification::query()
            ->whereIn('id', $notificationIds)
            ->with(['user'])
            ->orderByDesc('id')
            ->get();
    }

    public function sendToAll(
        string $title,
        string $body,
        string $type,
        array $data = []
    ): Notification {
        $notification = $this->create([
            'user_id' => null,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'data' => $data,
        ]);

        $userIds = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereNotNull('device_token')
            ->whereNotNull('tokenable_id')
            ->distinct()
            ->pluck('tokenable_id')
            ->map(static fn($id) => (int) $id)
            ->filter(static fn($id) => $id > 0)
            ->values()
            ->all();

        if (!empty($userIds)) {
            FirebaseService::sendToTokensAndStorage(
                users_ids: $userIds,
                notificationable: ['id' => null, 'type' => $type],
                title: $title,
                body: $body,
                replace: [],
                data: $data,
                isCustom: true,
                channelId: null,
                storeInDatabase: false
            );
        }

        return $notification;
    }
}

