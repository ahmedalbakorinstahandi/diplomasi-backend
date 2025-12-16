<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\SettingController;
use App\Services\MediaResolverService;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'general'], function () {
    Route::post('upload-image', [ImageController::class, 'uploadImage']);
    Route::post('upload-file', [ImageController::class, 'uploadFile']);

    Route::post('fetch-media', [ImageController::class, 'fetchMedia']);

    Route::get('settings/{idOrKey}', [SettingController::class, 'show']);

    Route::post('check-phone-number', [AuthController::class, 'checkPhoneNumber']);
});
