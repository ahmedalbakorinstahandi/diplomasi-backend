<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Services\System\SettingService;

class SettingController extends Controller
{
    protected $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }
}

