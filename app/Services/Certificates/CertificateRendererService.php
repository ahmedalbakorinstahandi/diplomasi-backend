<?php

namespace App\Services\Certificates;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

class CertificateRendererService
{
    public function resolveFontPath(): string
    {
        $candidates = array_merge(
            [config('certificate_template.font_path')],
            config('certificate_template.font_fallback_search', [])
        );

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('Certificate font file not found. Expected at storage/app/fonts/CrimsonText-SemiBold.ttf');
    }

    /**
     * Render name + date and return PNG binary.
     *
     * @param  array<string, mixed>  $mergedConfig
     */
    public function renderToBinary(string $templateAbsolutePath, array $mergedConfig, string $displayName, string $displayDate): string
    {
        if (!is_file($templateAbsolutePath)) {
            throw new \InvalidArgumentException('Template image not found: ' . $templateAbsolutePath);
        }

        $fontPath = $this->resolveFontPath();
        $manager = $this->makeImageManager();
        $image = $manager->read($templateAbsolutePath)->orient();

        $imgW = $image->width();
        $imgH = $image->height();

        $nameCfg = $mergedConfig['name'] ?? [];
        $dateCfg = $mergedConfig['date'] ?? [];
        $safe = $mergedConfig['safe_zones']['signature_and_seal'] ?? null;

        $nameColor = (string) ($nameCfg['color'] ?? '#0E2459');
        $dateColor = (string) ($dateCfg['color'] ?? '#0E2459');

        $nameMaxW = (int) floor((float) ($nameCfg['width'] ?? 0) * $imgW);
        $nameSize = $this->fitFontSize(
            $displayName,
            $fontPath,
            (int) ($nameCfg['max_font_size'] ?? 86),
            (int) ($nameCfg['min_font_size'] ?? 38),
            max(1, $nameMaxW)
        );

        $nameCenterX = (int) round(((float) ($nameCfg['x'] ?? 0) + (float) ($nameCfg['width'] ?? 0) / 2) * $imgW);
        $nameBaselineY = (int) round((float) ($nameCfg['baseline_y'] ?? 0) * $imgH);

        $image->text($displayName, $nameCenterX, $nameBaselineY, function (FontFactory $font) use ($fontPath, $nameSize, $nameColor) {
            $font->file($fontPath);
            $font->size((float) $nameSize);
            $font->color($nameColor);
            $font->align('center');
            $font->valign('bottom');
        });

        $dateMaxW = (int) floor((float) ($dateCfg['width'] ?? 0) * $imgW);
        $dateMin = (int) ($dateCfg['min_font_size'] ?? 24);
        $dateMax = (int) ($dateCfg['max_font_size'] ?? 44);

        $dateSize = $this->fitFontSize(
            $displayDate,
            $fontPath,
            $dateMax,
            $dateMin,
            max(1, $dateMaxW)
        );

        if (is_array($safe)) {
            $dateSize = $this->shrinkDateToAvoidSignature(
                $displayDate,
                $fontPath,
                $dateSize,
                $dateMin,
                $imgW,
                $imgH,
                $dateCfg,
                $safe
            );
        }

        $dateCenterX = (int) round(((float) ($dateCfg['x'] ?? 0) + (float) ($dateCfg['width'] ?? 0) / 2) * $imgW);
        $dateBaselineY = (int) round((float) ($dateCfg['baseline_y'] ?? 0) * $imgH);

        $image->text($displayDate, $dateCenterX, $dateBaselineY, function (FontFactory $font) use ($fontPath, $dateSize, $dateColor) {
            $font->file($fontPath);
            $font->size((float) $dateSize);
            $font->color($dateColor);
            $font->align('center');
            $font->valign('bottom');
        });

        return (string) $image->toPng();
    }

    /**
     * Render name + date and save as PNG file.
     *
     * @param  array<string, mixed>  $mergedConfig
     */
    public function renderToFile(string $templateAbsolutePath, array $mergedConfig, string $displayName, string $displayDate, string $outputAbsolutePath): void
    {
        $dir = dirname($outputAbsolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $binary = $this->renderToBinary($templateAbsolutePath, $mergedConfig, $displayName, $displayDate);
        file_put_contents($outputAbsolutePath, $binary);
    }

    private function makeImageManager(): ImageManager
    {
        // GD keeps font measurement (imageftbbox) consistent with Intervention's GD text drawing.
        return new ImageManager(new GdDriver());
    }

    private function fitFontSize(string $text, string $fontPath, int $maxSize, int $minSize, int $maxWidthPx): int
    {
        $maxSize = max(1, $maxSize);
        $minSize = max(1, min($minSize, $maxSize));

        for ($size = $maxSize; $size >= $minSize; $size--) {
            if ($this->textWidthPx($text, $fontPath, $size) <= $maxWidthPx) {
                return $size;
            }
        }

        return $minSize;
    }

    private function textWidthPx(string $text, string $fontPath, int $interventionFontSize): int
    {
        $native = round($interventionFontSize * 0.76, 6);
        $box = imageftbbox($native, 0, $fontPath, $text);
        if ($box === false) {
            return 0;
        }

        return (int) abs($box[2] - $box[0]);
    }

    private function textHeightPx(string $text, string $fontPath, int $interventionFontSize): int
    {
        $native = round($interventionFontSize * 0.76, 6);
        $box = imageftbbox($native, 0, $fontPath, $text);
        if ($box === false) {
            return 0;
        }

        return (int) abs($box[7] - $box[1]);
    }

    /**
     * @param  array<string, mixed>  $dateCfg
     * @param  array<string, float>  $sigNorm
     */
    private function shrinkDateToAvoidSignature(
        string $displayDate,
        string $fontPath,
        int $dateSize,
        int $dateMin,
        int $imgW,
        int $imgH,
        array $dateCfg,
        array $sigNorm
    ): int {
        $sigX = (float) ($sigNorm['x'] ?? 0) * $imgW;
        $sigY = (float) ($sigNorm['y'] ?? 0) * $imgH;
        $sigW = (float) ($sigNorm['width'] ?? 0) * $imgW;
        $sigH = (float) ($sigNorm['height'] ?? 0) * $imgH;

        $centerX = ((float) ($dateCfg['x'] ?? 0) + (float) ($dateCfg['width'] ?? 0) / 2) * $imgW;
        $baselineY = (float) ($dateCfg['baseline_y'] ?? 0) * $imgH;

        for ($size = $dateSize; $size >= $dateMin; $size--) {
            $tw = $this->textWidthPx($displayDate, $fontPath, $size);
            $th = $this->textHeightPx($displayDate, $fontPath, $size);
            $left = $centerX - $tw / 2;
            $top = $baselineY - $th;
            $rw = $tw;
            $rh = $th;

            if (!$this->rectsOverlap($left, $top, $rw, $rh, $sigX, $sigY, $sigW, $sigH)) {
                return $size;
            }
        }

        return $dateMin;
    }

    private function rectsOverlap(float $ax, float $ay, float $aw, float $ah, float $bx, float $by, float $bw, float $bh): bool
    {
        return !($ax + $aw < $bx || $bx + $bw < $ax || $ay + $ah < $by || $by + $bh < $ay);
    }
}
