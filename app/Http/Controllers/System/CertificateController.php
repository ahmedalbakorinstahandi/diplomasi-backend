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

        if ($shouldGenerateImage) {
            try {
                $certificate->load(['user', 'course', 'level']);
                if (!$certificate->user) {
                    throw new \Exception(trans('messages.certificate.user_data_not_found'));
                }
                if (!$certificate->course) {
                    throw new \Exception(trans('messages.certificate.course_data_not_found'));
                }

                $this->certificateService->ensureCertificateImage($certificate);

                Log::info('Certificate image ensured in verifyWeb', [
                    'certificate_id' => $certificate->id,
                    'certificate_code' => $certificateCode,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to ensure certificate image in verifyWeb', [
                    'certificate_id' => $certificate->id,
                    'certificate_code' => $certificateCode,
                    'error' => $e->getMessage(),
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
     * التحقق من صورة الشهادة وتوليدها إذا كانت مفقودة
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyImage(int $id)
    {
        CertificatePermission::canView();


        $certificate = $this->certificateService->show($id);


        $certificate = $this->certificateService->ensureCertificateImage($certificate);


        CertificatePermission::canShow($certificate);

        return ResponseService::response([
            'success' => true,
            'data' => $certificate,
            'message' => 'messages.certificate.verified',
            'resource' => CertificateResource::class,
            'status' => 200,
        ]);
    }

    /**
     * إعادة توليد شهادة المستوى ببيانات المستخدم الحالية
     * (يحذف الشهادة القديمة soft delete ثم ينشئ شهادة جديدة).
     */
    public function regenerate(int $id)
    {
        CertificatePermission::canView();

        $certificate = $this->certificateService->show($id);
        CertificatePermission::canShow($certificate);

        $regenerated = $this->certificateService->regenerateCertificate($certificate);

        return ResponseService::response([
            'success' => true,
            'data' => $regenerated,
            'message' => 'messages.certificate.verified',
            'resource' => CertificateResource::class,
            'status' => 200,
        ]);
    }
}
