<?php

use App\Http\Controllers\ImageController;
use App\Http\Controllers\System\SettingController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'general'], function () {
    // Public settings by key
    Route::get('settings/{key}', [SettingController::class, 'getByKey']);

    Route::post('upload-image', [ImageController::class, 'uploadImage']);
    Route::post('upload-file', [ImageController::class, 'uploadFile']);
});
