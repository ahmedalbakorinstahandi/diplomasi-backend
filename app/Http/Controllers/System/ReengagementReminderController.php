<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Permissions\System\ReengagementReminderPermission;
use App\Http\Requests\System\CreateReengagementReminderRequest;
use App\Http\Requests\System\UpdateReengagementReminderRequest;
use App\Http\Resources\System\ReengagementReminderResource;
use App\Http\Services\System\ReengagementReminderService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class ReengagementReminderController extends Controller
{
    public function __construct(
        protected ReengagementReminderService $reengagementReminderService
    ) {
    }

    public function index(Request $request)
    {
        ReengagementReminderPermission::canView();

        $reminders = $this->reengagementReminderService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'data' => $reminders,
            'meta' => true,
            'resource' => ReengagementReminderResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        ReengagementReminderPermission::canView();

        $reminder = $this->reengagementReminderService->show($id);
        ReengagementReminderPermission::canShow($reminder);

        return ResponseService::response([
            'success' => true,
            'data' => $reminder,
            'resource' => ReengagementReminderResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateReengagementReminderRequest $request)
    {
        ReengagementReminderPermission::canCreate();

        $reminder = $this->reengagementReminderService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $reminder,
            'message' => 'messages.reengagement_reminder.created',
            'status' => 201,
            'resource' => ReengagementReminderResource::class,
        ]);
    }

    public function update(UpdateReengagementReminderRequest $request, int $id)
    {
        ReengagementReminderPermission::canUpdate();

        $reminder = $this->reengagementReminderService->show($id);
        $reminder = $this->reengagementReminderService->update($request->validated(), $reminder);

        return ResponseService::response([
            'success' => true,
            'data' => $reminder,
            'message' => 'messages.reengagement_reminder.updated',
            'status' => 200,
            'resource' => ReengagementReminderResource::class,
        ]);
    }

    public function delete(int $id)
    {
        ReengagementReminderPermission::canDelete();

        $reminder = $this->reengagementReminderService->show($id);
        $this->reengagementReminderService->delete($reminder);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.reengagement_reminder.deleted',
            'status' => 200,
        ]);
    }
}
