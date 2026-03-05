<?php

use App\Http\Controllers\Billing\MoyasarWebhookController;
use App\Http\Controllers\System\AppUpdateController;
use App\Http\Controllers\System\CertificateController;
use App\Http\Controllers\System\ImageController;
use App\Http\Controllers\System\SettingController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'general'], function () {
    // Suggested app update check (call once per 24h from app; send X-App-Version header)
    Route::get('app-update-check', [AppUpdateController::class, 'checkSuggest']);

    // Public settings
    Route::get('settings/{idOrKey}', [SettingController::class, 'show']);
     Route::get('settings', [SettingController::class, 'index']);
    

    Route::post('upload-image', [ImageController::class, 'uploadImage']);
    Route::post('upload-file', [ImageController::class, 'uploadFile']);

    // Certificate Verification (Public)
    Route::get('certificates/verify/{certificateCode}', [CertificateController::class, 'verify']);
});
