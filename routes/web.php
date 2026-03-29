<?php

use App\Http\Controllers\System\CertificateController;
use App\Http\Controllers\Web\LegalWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Legal (iOS) — محتوى من الإعدادات لروابط App Store / المراجعة
Route::get('legal/ios/terms-of-use', [LegalWebController::class, 'iosTerms'])->name('legal.ios.terms');
Route::get('legal/ios/privacy-policy', [LegalWebController::class, 'iosPrivacy'])->name('legal.ios.privacy');

// Certificate Verification (Web View) - للمتصفحات (QR Code)
Route::get('certificates/verify/{certificateCode}', [CertificateController::class, 'verifyWeb'])->name('certificates.verify');

// PDF route removed - using PNG only now
// Route::get('certificates/{certificateCode}/pdf', [CertificateController::class, 'viewPdf'])->name('certificates.pdf');
