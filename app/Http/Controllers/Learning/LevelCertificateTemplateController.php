<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Learning\LevelPermission;
use App\Http\Requests\Learning\UpdateLevelCertificateConfigRequest;
use App\Http\Requests\Learning\UploadLevelCertificateTemplateRequest;
use App\Http\Resources\Learning\LevelResource;
use App\Http\Services\Learning\LevelCertificateTemplateService;
use App\Http\Services\Learning\LevelService;
use App\Services\ResponseService;
use Symfony\Component\HttpFoundation\Response;

class LevelCertificateTemplateController extends Controller
{
    public function __construct(
        protected LevelCertificateTemplateService $templateService,
        protected LevelService $levelService
    ) {}

    public function upload(UploadLevelCertificateTemplateRequest $request, int $id)
    {
        LevelPermission::canUpdate();

        $level = $this->levelService->show($id);
        $level = $this->templateService->uploadTemplate($level, $request->file('template'));

        return ResponseService::response([
            'success' => true,
            'data' => $level,
            'message' => 'messages.level.certificate_template_uploaded',
            'resource' => LevelResource::class,
            'status' => 200,
        ]);
    }

    public function destroy(int $id)
    {
        LevelPermission::canUpdate();

        $level = $this->levelService->show($id);
        $level = $this->templateService->deleteTemplate($level);

        return ResponseService::response([
            'success' => true,
            'data' => $level,
            'message' => 'messages.level.certificate_template_deleted',
            'resource' => LevelResource::class,
            'status' => 200,
        ]);
    }

    public function updateConfig(UpdateLevelCertificateConfigRequest $request, int $id)
    {
        LevelPermission::canUpdate();

        $level = $this->levelService->show($id);
        $level = $this->templateService->updateConfig($level, $request->validated()['certificate_template_config']);

        return ResponseService::response([
            'success' => true,
            'data' => $level,
            'message' => 'messages.level.certificate_template_config_updated',
            'resource' => LevelResource::class,
            'status' => 200,
        ]);
    }

    public function preview(int $id)
    {
        LevelPermission::canUpdate();

        $level = $this->levelService->show($id);
        $png = $this->templateService->renderPreviewBinary($level);

        return response($png, Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
        ]);
    }
}
