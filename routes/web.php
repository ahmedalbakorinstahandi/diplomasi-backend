<?php

use App\Http\Controllers\System\CertificateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Certificate Verification (Web View) - للمتصفحات (QR Code)
Route::get('certificates/verify/{certificateCode}', [CertificateController::class, 'verifyWeb'])->name('certificates.verify');

// Certificate PDF View - عرض PDF الشهادة مباشرة
Route::get('certificates/{certificateCode}/pdf', [CertificateController::class, 'viewPdf'])->name('certificates.pdf');
