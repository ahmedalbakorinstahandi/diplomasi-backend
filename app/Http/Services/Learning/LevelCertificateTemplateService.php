<?php

namespace App\Http\Services\Learning;

use App\Models\Learning\Level;
use App\Services\Certificates\CertificateRendererService;
use App\Services\Certificates\CertificateTemplateConfigMerger;
use App\Services\MessageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

class LevelCertificateTemplateService
{
    public const DISK = 'public';

    public const PREVIEW_NAME = 'Ahmed Albakor';

    public const PREVIEW_DATE = '13 April 2026';

    public function __construct(
        protected CertificateRendererService $renderer
    ) {}

    public function uploadTemplate(Level $level, UploadedFile $file): Level
    {
        $maxKb = (int) config('certificate_template.max_upload_kb', 8192);
        if ($file->getSize() > $maxKb * 1024) {
            MessageService::abort(422, 'messages.level.certificate_template_file_too_large');
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
            MessageService::abort(422, 'messages.level.certificate_template_invalid_type');
        }

        $this->deleteTemplateFiles($level);

        $dir = 'level-certificate-templates/' . $level->id;
        $filename = 'template.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $path = $file->storeAs($dir, $filename, self::DISK);

        $full = Storage::disk(self::DISK)->path($path);
        [$w, $h] = $this->readImageDimensions($full);

        $config = CertificateTemplateConfigMerger::merge($level->certificate_template_config);
        $config['template_width'] = $w;
        $config['template_height'] = $h;

        $level->certificate_template_path = $path;
        $level->certificate_template_config = $config;
        $level->save();

        return $level->fresh();
    }

    public function deleteTemplate(Level $level): Level
    {
        $this->deleteTemplateFiles($level);
        $level->certificate_template_path = null;
        $level->certificate_template_config = null;
        $level->save();

        return $level->fresh();
    }

    public function updateConfig(Level $level, array $config): Level
    {
        $merged = CertificateTemplateConfigMerger::merge($config);
        $level->certificate_template_config = $merged;
        $level->save();

        return $level->fresh();
    }

    /**
     * Raw PNG bytes for preview (uses merged config + sample name/date).
     */
    public function renderPreviewBinary(Level $level): string
    {
        if (!$level->certificate_template_path) {
            MessageService::abort(400, 'messages.level.certificate_template_missing');
        }

        $full = Storage::disk(self::DISK)->path($level->certificate_template_path);
        if (!is_file($full)) {
            MessageService::abort(400, 'messages.level.certificate_template_file_missing');
        }

        $merged = CertificateTemplateConfigMerger::merge($level->certificate_template_config);

        return $this->renderer->renderToBinary(
            $full,
            $merged,
            self::PREVIEW_NAME,
            self::PREVIEW_DATE
        );
    }

    protected function deleteTemplateFiles(Level $level): void
    {
        if ($level->certificate_template_path) {
            Storage::disk(self::DISK)->delete($level->certificate_template_path);
        }
        Storage::disk(self::DISK)->deleteDirectory('level-certificate-templates/' . $level->id);
    }

    /**
     * @return array{0:int,1:int}
     */
    protected function readImageDimensions(string $absolutePath): array
    {
        $manager = new ImageManager(new GdDriver());
        try {
            $img = $manager->read($absolutePath);

            return [$img->width(), $img->height()];
        } catch (\Throwable) {
            $info = @getimagesize($absolutePath);
            if ($info !== false) {
                return [(int) $info[0], (int) $info[1]];
            }
        }

        return [2048, 1448];
    }
}
