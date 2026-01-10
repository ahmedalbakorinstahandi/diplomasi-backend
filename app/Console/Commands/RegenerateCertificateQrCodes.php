<?php

namespace App\Console\Commands;

use App\Http\Services\System\CertificateService;
use App\Models\System\Certificate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class RegenerateCertificateQrCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:regenerate-qr {certificate_id?} {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'إعادة توليد QR Code للشهادات';

    protected CertificateService $certificateService;

    public function __construct(CertificateService $certificateService)
    {
        parent::__construct();
        $this->certificateService = $certificateService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $certificateId = $this->argument('certificate_id');
        $all = $this->option('all');

        if ($certificateId) {
            // إعادة توليد QR Code لشهادة محددة
            $certificate = Certificate::find($certificateId);
            if (!$certificate) {
                $this->error("الشهادة {$certificateId} غير موجودة");
                return 1;
            }
            $this->regenerateQrCode($certificate);
        } elseif ($all) {
            // إعادة توليد QR Code لجميع الشهادات
            $certificates = Certificate::whereNotNull('certificate_code')->get();
            $this->info("عدد الشهادات: " . $certificates->count());
            $this->line('');

            $progressBar = $this->output->createProgressBar($certificates->count());
            $progressBar->start();

            $successCount = 0;
            $failCount = 0;

            foreach ($certificates as $certificate) {
                try {
                    $this->regenerateQrCode($certificate, false);
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                    $this->line('');
                    $this->error("  ❌ فشل في إعادة توليد QR Code للشهادة ID: {$certificate->id} - {$e->getMessage()}");
                }
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->line('');
            $this->line('');
            $this->info("✅ نجح: {$successCount}");
            $this->error("❌ فشل: {$failCount}");
        } else {
            // إعادة توليد QR Code للشهادات التي لا يوجد لها QR Code
            $certificates = Certificate::whereNotNull('certificate_code')
                ->where(function ($query) {
                    $query->whereNull('qr_code')
                        ->orWhere('qr_code', '');
                })
                ->get();

            if ($certificates->isEmpty()) {
                $this->info("لا توجد شهادات تحتاج لإعادة توليد QR Code");
                return 0;
            }

            $this->info("عدد الشهادات التي تحتاج لإعادة توليد QR Code: " . $certificates->count());
            $this->line('');

            $progressBar = $this->output->createProgressBar($certificates->count());
            $progressBar->start();

            $successCount = 0;
            $failCount = 0;

            foreach ($certificates as $certificate) {
                try {
                    $this->regenerateQrCode($certificate, false);
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                    $this->line('');
                    $this->error("  ❌ فشل في إعادة توليد QR Code للشهادة ID: {$certificate->id} - {$e->getMessage()}");
                }
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->line('');
            $this->line('');
            $this->info("✅ نجح: {$successCount}");
            $this->error("❌ فشل: {$failCount}");
        }

        return 0;
    }

    private function regenerateQrCode(Certificate $certificate, bool $verbose = true): void
    {
        if ($verbose) {
            $this->info("إعادة توليد QR Code للشهادة ID: {$certificate->id}");
            $this->info("  Certificate Code: {$certificate->certificate_code}");
        }

        try {
            // توليد QR Code
            $qrCodePath = $this->certificateService->generateCertificateQrCode($certificate);

            // تحديث الشهادة
            $certificate->qr_code = $qrCodePath;
            $certificate->save();

            // التحقق من أن الملف تم إنشاؤه
            $fullPath = storage_path('app/public/' . $qrCodePath);
            if (!File::exists($fullPath)) {
                throw new \Exception("QR Code file was not created at: {$fullPath}");
            }

            if ($verbose) {
                $this->info("  ✅ تم إنشاء QR Code بنجاح");
                $this->info("  📁 المسار: {$qrCodePath}");
                $this->info("  📁 المسار الكامل: {$fullPath}");
                $this->info("  📏 الحجم: " . File::size($fullPath) . " bytes");
            }
        } catch (\Exception $e) {
            if ($verbose) {
                $this->error("  ❌ خطأ: " . $e->getMessage());
                $this->error("  📍 Trace: " . $e->getTraceAsString());
            }
            Log::error("Failed to regenerate QR code", [
                'certificate_id' => $certificate->id,
                'certificate_code' => $certificate->certificate_code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
