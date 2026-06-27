<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Content\ContactMessagePermission;
use App\Http\Resources\Content\ContactMessageResource;
use App\Http\Services\Content\ContactService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function __construct(
        protected ContactService $contactService
    ) {}

    public function index(Request $request, $message = null)
    {
        ContactMessagePermission::canView();

        $messages = $this->contactService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'message' => $message,
            'data' => $messages,
            'meta' => true,
            'resource' => ContactMessageResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        ContactMessagePermission::canView();

        $contactMessage = $this->contactService->show($id);

        return ResponseService::response([
            'success' => true,
            'data' => $contactMessage,
            'resource' => ContactMessageResource::class,
            'status' => 200,
        ]);
    }

    public function markAsRead(int $id)
    {
        ContactMessagePermission::canUpdate();

        $contactMessage = $this->contactService->show($id);
        $contactMessage = $this->contactService->markAsRead($contactMessage);

        return ResponseService::response([
            'success' => true,
            'data' => $contactMessage,
            'message' => 'messages.contact_message.marked_read',
            'resource' => ContactMessageResource::class,
            'status' => 200,
        ]);
    }
}
