<?php

use App\Http\Controllers\Billing\GeideaWebhookController;
use App\Http\Controllers\System\CertificateController;
use App\Http\Controllers\System\ImageController;
use App\Http\Controllers\System\SettingController;
use Illuminate\Support\Facades\Route;

// Geidea webhook (public, no auth)
Route::post('webhooks/geidea/callback', [GeideaWebhookController::class, 'callback']);

Route::group(['prefix' => 'general'], function () {
    // Public settings
    Route::get('settings/{idOrKey}', [SettingController::class, 'show']);
     Route::get('settings', [SettingController::class, 'index']);
    

    Route::post('upload-image', [ImageController::class, 'uploadImage']);
    Route::post('upload-file', [ImageController::class, 'uploadFile']);

    // Certificate Verification (Public)
    Route::get('certificates/verify/{certificateCode}', [CertificateController::class, 'verify']);
});
