<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Permissions\System\SettingPermission;
use App\Http\Requests\System\CreateSettingRequest;
use App\Http\Requests\System\UpdateManySettingsRequest;
use App\Http\Requests\System\UpdateSettingRequest;
use App\Http\Resources\System\SettingResource;
use App\Http\Services\System\SettingService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    protected $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function index(Request $request, $message = null)
    {
        SettingPermission::canView();

        $settings = $this->settingService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'data' => $settings,
            'message' => $message,
            'meta' => true,
            'resource' => SettingResource::class,
            'status' => 200,
        ]);
    }

    public function show($idOrKey)
    {
        // SettingPermission::canView();

        $setting = $this->settingService->show($idOrKey);
        
        SettingPermission::canShow($setting);

        return ResponseService::response([
            'success' => true,
            'data' => $setting,
            'resource' => SettingResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateSettingRequest $request)
    {
        SettingPermission::canCreate();

        $setting = $this->settingService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $setting,
            'message' => 'messages.setting.created',
            'status' => 201,
            'resource' => SettingResource::class,
        ]);
    }

    public function update(UpdateSettingRequest $request, $idOrKey)
    {
        SettingPermission::canUpdate();

        $setting = $this->settingService->show($idOrKey);

        $setting = $this->settingService->update($request->validated(), $setting);

        return ResponseService::response([
            'success' => true,
            'data' => $setting,
            'message' => 'messages.setting.updated',
            'status' => 200,
            'resource' => SettingResource::class,
        ]);
    }

    public function delete($idOrKey)
    {
        SettingPermission::canDelete();

        $setting = $this->settingService->show($idOrKey);

        $this->settingService->delete($setting);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.setting.deleted',
            'status' => 200,
        ]);
    }

    public function updateMany(UpdateManySettingsRequest $request)
    {
        SettingPermission::canUpdateMany();

        $this->settingService->updateMany($request->validated());

        return $this->index(request(), 'messages.setting.updated_many');
    }

    public function getByKey(string $key)
    {
        SettingPermission::canView();

        $setting = $this->settingService->getByKey($key);
        SettingPermission::canShow($setting);

        return ResponseService::response([
            'success' => true,
            'data' => $setting,
            'resource' => SettingResource::class,
            'status' => 200,
        ]);
    }
}
