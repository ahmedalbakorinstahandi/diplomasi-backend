<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Permissions\System\NotificationPermission;
use App\Http\Requests\System\CreateNotificationRequest;
use App\Http\Requests\System\MarkNotificationAsReadRequest;
use App\Http\Resources\System\NotificationResource;
use App\Http\Services\System\NotificationService;
use App\Models\Users\User;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        NotificationPermission::canView();

        $notifications = $this->notificationService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'data' => $notifications,
            'meta' => true,
            'resource' => NotificationResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        NotificationPermission::canView();

        $notification = $this->notificationService->show($id);
        NotificationPermission::canShow($notification);

        return ResponseService::response([
            'success' => true,
            'data' => $notification,
            'resource' => NotificationResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateNotificationRequest $request)
    {
        NotificationPermission::canCreate();

        $notification = $this->notificationService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $notification,
            'message' => 'messages.notification.created',
            'status' => 201,
            'resource' => NotificationResource::class,
        ]);
    }

    public function update(CreateNotificationRequest $request, int $id)
    {
        NotificationPermission::canUpdate();

        $notification = $this->notificationService->show($id);

        $notification = $this->notificationService->update($request->validated(), $notification);

        return ResponseService::response([
            'success' => true,
            'data' => $notification,
            'message' => 'messages.notification.updated',
            'status' => 200,
            'resource' => NotificationResource::class,
        ]);
    }

    public function delete(int $id)
    {
        NotificationPermission::canDelete();

        $notification = $this->notificationService->show($id);

        $this->notificationService->delete($notification);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.notification.deleted',
            'status' => 200,
        ]);
    }

    public function markAsRead(int $id, MarkNotificationAsReadRequest $request)
    {
        $notification = $this->notificationService->show($id);
        NotificationPermission::canMarkAsRead($notification);

        $notification = $this->notificationService->markAsRead($notification);

        return ResponseService::response([
            'success' => true,
            'data' => $notification,
            'message' => 'messages.notification.marked_as_read',
            'status' => 200,
            'resource' => NotificationResource::class,
        ]);
    }

    public function markAllAsRead()
    {
        NotificationPermission::canMarkAllAsRead();

        $user = User::auth();

        $this->notificationService->markAllAsRead($user->id);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.notification.all_marked_as_read',
            'status' => 200,
        ]);
    }

    public function getUnreadCount()
    {
        NotificationPermission::canUnreadCount();

        $user = User::auth();

        $count = $this->notificationService->getUnreadCount($user->id);

        return ResponseService::response([
            'success' => true,
            'data' => ['count' => $count],
            'status' => 200,
        ]);
    }
}

