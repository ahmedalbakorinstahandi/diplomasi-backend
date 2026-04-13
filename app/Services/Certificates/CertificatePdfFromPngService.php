<?php

namespace App\Services\Certificates;

use Mpdf\Mpdf;
use Mpdf\MpdfException;

class CertificatePdfFromPngService
{
    /**
     * Single-page PDF containing the full-resolution PNG.
     *
     * @throws MpdfException
     */
    public function createFromPngFile(string $pngAbsolutePath, string $outputPdfAbsolutePath): void
    {
        if (!is_file($pngAbsolutePath)) {
            throw new \InvalidArgumentException('PNG not found');
        }

        $size = @getimagesize($pngAbsolutePath);
        $pxW = $size[0] ?? 2048;
        $pxH = $size[1] ?? 1448;
        // Page size in mm at 96 DPI so the image fits without unintended scaling.
        $mmW = $pxW / 96 * 25.4;
        $mmH = $pxH / 96 * 25.4;

        $dataUri = 'data:image/png;base64,' . base64_encode((string) file_get_contents($pngAbsolutePath));

        $mpdf = new Mpdf([
            'tempDir' => storage_path('framework/cache'),
            'mode' => 'utf-8',
            'format' => [$mmW, $mmH],
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);
        $mpdf->SetTitle('Certificate');
        $html = '<div style="margin:0;padding:0;"><img src="' . $dataUri . '" style="width:100%;height:100%;display:block;" alt="" /></div>';
        $mpdf->WriteHTML($html);

        $dir = dirname($outputPdfAbsolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $mpdf->Output($outputPdfAbsolutePath, 'F');
    }
}
