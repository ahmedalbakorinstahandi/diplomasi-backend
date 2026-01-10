<?php

use App\Http\Controllers\System\ImageController;
use App\Http\Controllers\System\SettingController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'general'], function () {
    // Public settings
    Route::get('settings/{idOrKey}', [SettingController::class, 'show']);
     Route::get('settings', [SettingController::class, 'index']);
    

    Route::post('upload-image', [ImageController::class, 'uploadImage']);
    Route::post('upload-file', [ImageController::class, 'uploadFile']);
});
