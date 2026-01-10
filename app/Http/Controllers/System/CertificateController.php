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
            return response()->view('certificates.invalid', [
                'message' => $result['message'] ?? 'الشهادة غير موجودة',
            ], 404);
        }

        // عرض صفحة الشهادة
        return response()->view('certificates.show', [
            'certificate' => $result['certificate'],
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
}
