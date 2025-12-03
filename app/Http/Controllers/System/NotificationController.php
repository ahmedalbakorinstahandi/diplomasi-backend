<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Services\System\NotificationService;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
}

