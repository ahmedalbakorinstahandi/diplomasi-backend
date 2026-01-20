<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Permissions\System\CertificatePermission;
use App\Http\Requests\System\IssueCertificateRequest;
use App\Http\Requests\System\RevokeCertificateRequest;
use App\Http\Resources\System\CertificateResource;
use App\Http\Services\System\CertificateService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CertificateController extends Controller
{
    protected $certificateService;

    public function __construct(CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    public function index(Request $request)
    {
        CertificatePermission::canView();

        $certificates = $this->certificateService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'data' => $certificates,
            'meta' => true,
            'resource' => CertificateResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        CertificatePermission::canView();

        $certificate = $this->certificateService->show($id);
        CertificatePermission::canShow($certificate);

        return ResponseService::response([
            'success' => true,
            'data' => $certificate,
            'resource' => CertificateResource::class,
            'status' => 200,
        ]);
    }

    public function issue(IssueCertificateRequest $request)
    {
        CertificatePermission::canIssue();

        $certificate = $this->certificateService->issueCertificate(
            $request->user_id,
            $request->course_id,
            $request->level_id
        );

        return ResponseService::response([
            'success' => true,
            'data' => $certificate,
            'message' => 'messages.certificate.issued',
            'status' => 201,
            'resource' => CertificateResource::class,
        ]);
    }

    public function download(int $id)
    {
        CertificatePermission::canView();

        $certificate = $this->certificateService->show($id);
        CertificatePermission::canDownload($certificate);

        return $this->certificateService->downloadCertificateImage($id);
    }

    /**
     * التحقق من الشهادة (API) - يعيد JSON
     */
    public function verify(string $certificateCode)
    {
        $result = $this->certificateService->verifyCertificate($certificateCode);

        return ResponseService::response([
            'success' => $result['valid'],
            'data' => $result,
            'status' => $result['valid'] ? 200 : 404,
        ]);
    }

    /**
     * التحقق من الشهادة (Web View) - للمتصفحات (QR Code)
     * يعرض صفحة ويب جميلة للشهادة
     */
    public function verifyWeb(string $certificateCode)
    {
        $result = $this->certificateService->verifyCertificate($certificateCode);

        // إذا كانت الشهادة غير صحيحة، عرض صفحة خطأ
        if (!$result['valid']) {
            $messageKey = $result['message'] ?? 'messages.certificate.not_found';
            return response()->view('certificates.invalid', [
                'message' => trans($messageKey),
            ], 404);
        }

        $certificate = $result['certificate'];

        // التحقق من وجود صورة PNG وتوليدها من PDF إذا لم تكن موجودة
        $shouldGenerateImage = false;
        
        if (!$certificate->image_url) {
            // الصورة غير موجودة في قاعدة البيانات
            $shouldGenerateImage = true;
        } else {
            // التحقق من وجود الملف الفعلي
            $imagePath = storage_path('app/public/' . $certificate->image_url);
            if (!file_exists($imagePath)) {
                // الملف غير موجود على الخادم
                $shouldGenerateImage = true;
            }
        }

        // توليد PNG من PDF إذا لزم الأمر
        if ($shouldGenerateImage) {
            try {
                $certificate->load(['user', 'course', 'level']); // تحميل العلاقات المطلوبة
                
                // التحقق من وجود البيانات المطلوبة
                if (!$certificate->user) {
                    throw new \Exception(trans('messages.certificate.user_data_not_found'));
                }
                if (!$certificate->course) {
                    throw new \Exception(trans('messages.certificate.course_data_not_found'));
                }
                
                // توليد PNG من PDF (يدعم العربية بشكل ممتاز)
                $imagePath = $this->certificateService->generateCertificateImageFromPdf($certificate);
                if ($imagePath) {
                    $certificate->image_url = $imagePath;
                    $certificate->save();
                }
                
                Log::info('Certificate PNG generated from PDF successfully in verifyWeb', [
                    'certificate_id' => $certificate->id,
                    'certificate_code' => $certificateCode,
                    'image_path' => $imagePath,
                ]);
            } catch (\Throwable $e) {
                // في حالة فشل توليد الصورة، نكمل العرض بدون الصورة
                Log::error('Failed to generate certificate PNG from PDF in verifyWeb', [
                    'certificate_id' => $certificate->id,
                    'certificate_code' => $certificateCode,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => substr($e->getTraceAsString(), 0, 1000),
                ]);
            }
        }

        // إعادة تحميل الشهادة للحصول على image_url المحدث
        $certificate->refresh();

        // عرض صفحة الشهادة
        return response()->view('certificates.show', [
            'certificate' => $certificate,
            'user_name' => $result['user_name'],
            'course_title' => $result['course_title'],
            'level_title' => $result['level_title'],
            'issued_at' => $result['issued_at'],
            'certificate_code' => $certificateCode,
        ]);
    }

    public function revoke(int $id, RevokeCertificateRequest $request)
    {
        CertificatePermission::canRevoke();

        $certificate = $this->certificateService->revokeCertificate($id, $request->reason);

        return ResponseService::response([
            'success' => true,
            'data' => $certificate,
            'message' => 'messages.certificate.revoked',
            'status' => 200,
            'resource' => CertificateResource::class,
        ]);
    }

    /**
     * عرض PDF الشهادة مباشرة في المتصفح
     */
    // PDF method removed - using PNG only now
    // public function viewPdf(string $certificateCode)
    // {
    //     // البحث عن الشهادة باستخدام الكود
    //     $certificate = $this->certificateService->verifyCertificate($certificateCode);
    //     
    //     if (!$certificate['valid']) {
    //         \App\Services\MessageService::abort(404, 'messages.certificate.not_found');
    //     }
    //
    //     return $this->certificateService->generateCertificatePdf($certificate['certificate']);
    // }

    /**
     * التحقق من الشهادة وتوليد الصورة إذا لم تكن موجودة
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrIssue(\Illuminate\Http\Request $request)
    {
        // التحقق من صحة البيانات
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
            'level_id' => 'nullable|integer|exists:levels,id',
        ]);

        $result = $this->certificateService->verifyAndGenerateImage(
            $validated['user_id'],
            $validated['course_id'],
            $validated['level_id'] ?? null
        );

        return \App\Services\MessageService::success(
            $result['message'] ?? trans('messages.certificate.ready'),
            $result
        );
    }
}
