<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ContactFormRequest;
use App\Http\Services\Content\ContactService;
use App\Services\ResponseService;

class ContactController extends Controller
{
    public function __construct(
        protected ContactService $contactService
    ) {}

    public function store(ContactFormRequest $request)
    {
        $this->contactService->store(
            $request->validated(),
            $request->ip()
        );

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.contact.sent',
            'status' => 201,
        ]);
    }
}
