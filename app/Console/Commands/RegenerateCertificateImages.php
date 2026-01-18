<?php

namespace App\Console\Commands;

use App\Http\Services\System\CertificateService;
use App\Models\System\Certificate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class RegenerateCertificateImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:regenerate-image {certificate_id?} {--all} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'إعادة توليد صور الشهادات';

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
        $force = $this->option('force');

        // التحقق من وجود صورة القالب
        $templatePath = storage_path('app/public/certificates/templates/certificate-template.png');
        if (!File::exists($templatePath)) {
            $this->error("❌ صورة القالب غير موجودة!");
            $this->info("📁 الموقع المطلوب: {$templatePath}");
            $this->info("💡 يرجى رفع صورة القالب أولاً إلى: storage/app/public/certificates/templates/certificate-template.png");
            return 1;
        }

        $this->info("✅ صورة القالب موجودة: {$templatePath}");

        if ($certificateId) {
            // إعادة توليد صورة شهادة محددة
            $certificate = Certificate::find($certificateId);
            if (!$certificate) {
                $this->error("❌ الشهادة {$certificateId} غير موجودة");
                return 1;
            }
            $this->regenerateImage($certificate, $force);
        } elseif ($all) {
            // إعادة توليد صور جميع الشهادات
            $certificates = Certificate::whereNotNull('certificate_code')->get();
            $this->info("📊 عدد الشهادات: " . $certificates->count());
            $this->line('');

            $progressBar = $this->output->createProgressBar($certificates->count());
            $progressBar->start();

            $successCount = 0;
            $failCount = 0;
            $skippedCount = 0;

            foreach ($certificates as $certificate) {
                try {
                    $result = $this->regenerateImage($certificate, $force, false);
                    if ($result === 'skipped') {
                        $skippedCount++;
                    } else {
                        $successCount++;
                    }
                } catch (\Exception $e) {
                    $failCount++;
                    $this->line('');
                    $this->error("  ❌ فشل في إعادة توليد صورة الشهادة ID: {$certificate->id} - {$e->getMessage()}");
                }
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->line('');
            $this->line('');
            $this->info("✅ نجح: {$successCount}");
            $this->warn("⏭️  تم التخطي: {$skippedCount}");
            $this->error("❌ فشل: {$failCount}");
        } else {
            $this->info("📝 استخدام الأمر:");
            $this->line("  - لإعادة توليد صورة شهادة محددة: php artisan certificates:regenerate-image {id}");
            $this->line("  - لإعادة توليد صور جميع الشهادات: php artisan certificates:regenerate-image --all");
            $this->line("  - لإعادة توليد حتى لو كانت موجودة: php artisan certificates:regenerate-image --all --force");
        }

        return 0;
    }

    private function regenerateImage(Certificate $certificate, bool $force = false, bool $verbose = true): string
    {
        if ($verbose) {
            $this->info("🔄 إعادة توليد صورة الشهادة ID: {$certificate->id}");
            $this->info("  📋 Certificate Code: {$certificate->certificate_code}");
        }

        // التحقق من وجود صورة مسبقاً
        if ($certificate->image_url && !$force) {
            $existingPath = storage_path('app/public/' . $certificate->image_url);
            if (File::exists($existingPath)) {
                if ($verbose) {
                    $this->warn("  ⏭️  صورة موجودة مسبقاً، تم التخطي. استخدم --force لإعادة التوليد");
                }
                return 'skipped';
            }
        }

        try {
            // تحميل العلاقات المطلوبة
            $certificate->load(['user', 'course', 'level']);

            // توليد صورة الشهادة
            $imagePath = $this->certificateService->generateCertificateImage($certificate);

            // تحديث الشهادة
            $certificate->image_url = $imagePath;
            $certificate->save();

            // التحقق من أن الملف تم إنشاؤه
            $fullPath = storage_path('app/public/' . $imagePath);
            if (!File::exists($fullPath)) {
                throw new \Exception(trans('messages.certificate.image_not_created') . ": {$fullPath}");
            }

            if ($verbose) {
                $this->info("  ✅ تم إنشاء صورة الشهادة بنجاح");
                $this->info("  📁 المسار: {$imagePath}");
                $this->info("  📁 المسار الكامل: {$fullPath}");
                $this->info("  📏 الحجم: " . File::size($fullPath) . " bytes");
                
                // عرض أبعاد الصورة
                try {
                    $imageInfo = getimagesize($fullPath);
                    if ($imageInfo) {
                        $this->info("  📐 الأبعاد: {$imageInfo[0]} x {$imageInfo[1]} pixels");
                    }
                } catch (\Exception $e) {
                    // تجاهل الخطأ
                }
            }

            return 'success';
        } catch (\Exception $e) {
            if ($verbose) {
                $this->error("  ❌ خطأ: " . $e->getMessage());
                $this->error("  📍 Trace: " . $e->getTraceAsString());
            }
            Log::error("Failed to regenerate certificate image", [
                'certificate_id' => $certificate->id,
                'certificate_code' => $certificate->certificate_code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
