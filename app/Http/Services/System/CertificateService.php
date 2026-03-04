<?php

namespace App\Http\Services\System;

use App\Http\Notifications\CertificateNotification;
use App\Http\Permissions\System\CertificatePermission;
use App\Models\Learning\Course;
use App\Models\Learning\Level;
use App\Models\Progress\UserCourse;
use App\Models\Progress\UserLevelProgress;
use App\Models\System\Certificate;
use App\Services\ArabicTextRenderer;
use App\Services\CertificateEnglishHelper;
use App\Services\FilterService;
use App\Services\ImageService;
use App\Services\MessageService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateService
{
    /** Single authoritative certificate template (English design) - relative to public/ */
    public const TEMPLATE_PATH = 'images/certificate-template.png';

    public function index($filters = [])
    {
        $query = Certificate::query()->with(['user', 'course', 'level']);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'issued_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = ['certificate_code'];
        $numericFields = [];
        $dateFields = ['issued_at', 'created_at'];
        $exactMatchFields = ['user_id', 'course_id', 'level_id'];
        $inFields = [];

        $query = CertificatePermission::filterIndex($query);

        $query = FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );

        return $query;
    }

    public function show(int $id)
    {
        $certificate = Certificate::where('id', $id)->first();
        if (!$certificate) {
            MessageService::abort(404, 'messages.certificate.not_found');
        }

        $certificate->load(['user', 'course', 'level']);

        return $certificate;
    }

    public function getUserCertificates(int $userId, $filters = [])
    {
        $query = Certificate::query()
            ->where('user_id', $userId)
            ->with(['course', 'level']);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'issued_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = ['certificate_code'];
        $numericFields = [];
        $dateFields = ['issued_at'];
        $exactMatchFields = ['course_id', 'level_id'];
        $inFields = [];

        $query = FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );

        return $query;
    }

    /**
     * التحقق من أهلية المستخدم للحصول على شهادة
     */
    public function checkCertificateEligibility(int $userId, int $courseId, ?int $levelId = null): void
    {
        // سيناريو 1: إكمال الكورس (level_id = null) - يحتاج UserCourse
        if ($levelId === null) {
            $userCourse = UserCourse::where('user_id', $userId)
                ->where('course_id', $courseId)
                ->first();

            if (!$userCourse) {
                MessageService::abort(400, 'messages.certificate.eligibility.user_not_registered_in_course');
            }
            if ($userCourse->status !== 'completed') {
                MessageService::abort(400, 'messages.certificate.eligibility.course_not_completed');
            }

            if (!$userCourse->completed_at) {
                MessageService::abort(400, 'messages.certificate.eligibility.completion_date_not_found');
            }

            // التحقق من أن جميع المستويات مكتملة
            $course = Course::find($courseId);
            if (!$course) {
                MessageService::abort(400, 'messages.certificate.eligibility.course_not_found');
            }

            $levels = $course->levels()->get();
            foreach ($levels as $level) {
                $userLevelProgress = UserLevelProgress::where('user_id', $userId)
                    ->where('level_id', $level->id)
                    ->first();

                if (!$userLevelProgress || $userLevelProgress->status !== 'completed') {
                    MessageService::abort(400, 'messages.certificate.eligibility.some_levels_not_completed');
                }
            }

            // التحقق من عدم وجود شهادة سابقة للكورس
            $existingCertificate = Certificate::where('user_id', $userId)
                ->where('course_id', $courseId)
                ->whereNull('level_id')
                ->first();

            if ($existingCertificate) {
                MessageService::abort(400, 'messages.certificate.eligibility.certificate_already_issued_for_course');
            }

            return; // مؤهل
        }

        // سيناريو 2: إكمال مستوى محدد (level_id محدد)
        $level = Level::find($levelId);
        if (!$level) {
            MessageService::abort(400, 'messages.certificate.eligibility.level_not_found');
        }

        if ($level->course_id != $courseId) {
            MessageService::abort(400, 'messages.certificate.eligibility.level_not_belongs_to_course');
        }

        if (!$level->has_certificate) {
            MessageService::abort(400, 'messages.certificate.eligibility.level_has_no_certificate');
        }

        $userLevelProgress = UserLevelProgress::where('user_id', $userId)
            ->where('level_id', $levelId)
            ->first();

        if (!$userLevelProgress) {
            MessageService::abort(400, 'messages.certificate.eligibility.user_not_registered_in_level');
        }

        if ($userLevelProgress->status !== 'completed') {
            MessageService::abort(400, 'messages.certificate.eligibility.level_not_completed');
        }

        if (!$userLevelProgress->completed_at) {
            MessageService::abort(400, 'messages.certificate.eligibility.level_completion_date_not_found');
        }

        // إنشاء UserCourse تلقائياً إذا لم يكن موجوداً (لإصدار شهادة المستوى)
        // هذا يضمن أن المستخدم يعتبر مسجل في الكورس عند إكمال مستوى
        $userCourse = UserCourse::firstOrNew([
            'user_id' => $userId,
            'course_id' => $courseId,
        ]);

        if (!$userCourse->exists) {
            // إنشاء UserCourse جديد مع status = 'active' إذا لم يكن موجوداً
            $userCourse->status = 'active';
            if (!$userCourse->started_at) {
                // استخدام تاريخ بدء المستوى أو تاريخ إكماله
                $userCourse->started_at = $userLevelProgress->started_at ?? $userLevelProgress->completed_at ?? now();
            }
            $userCourse->save();
        }

        // التحقق من عدم وجود شهادة سابقة لهذا المستوى
        $existingCertificate = Certificate::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('level_id', $levelId)
            ->first();

        if ($existingCertificate) {
            MessageService::abort(400, 'messages.certificate.eligibility.certificate_already_issued_for_level');
        }

        return; // مؤهل
    }

    /**
     * إصدار شهادة جديدة
     */
    public function issueCertificate(int $userId, int $courseId, ?int $levelId = null)
    {
        // التحقق من الأهلية (ستستدعي MessageService::abort تلقائياً عند الفشل)
        $this->checkCertificateEligibility($userId, $courseId, $levelId);

        // توليد كود الشهادة
        $certificateCode = $this->generateCertificateCode($userId, $courseId, $levelId);

        // إنشاء سجل الشهادة في قاعدة البيانات (مؤقتاً قبل توليد الصورة)
        $certificate = Certificate::create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'level_id' => $levelId,
            'certificate_code' => $certificateCode,
            'issued_at' => now(),
            'template_path' => self::TEMPLATE_PATH,
        ]);

        // توليد QR Code
        $qrCodePath = $this->generateCertificateQrCode($certificate);
        $certificate->qr_code = $qrCodePath;
        $certificate->save();

        // محاولة توليد PNG من PDF (اختياري - للعرض كصورة)
        try {
            $imagePath = $this->generateCertificateImageFromPdf($certificate);
            if ($imagePath) {
                $certificate->image_url = $imagePath;
                $certificate->save();
            }
        } catch (\Exception $e) {
            // إذا فشل تحويل PDF إلى PNG، لا مشكلة - PDF يعمل بشكل ممتاز
            Log::warning("Failed to generate PNG from PDF (optional)", [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
            ]);
        }

        // إرسال إشعار للمستخدم
        CertificateNotification::issued($certificate);

        return $this->show($certificate->id);
    }

    /**
     * توليد كود شهادة فريد
     */
    public function generateCertificateCode(int $userId, int $courseId, ?int $levelId = null): string
    {
        do {
            $timestamp = now()->format('YmdHis');
            $random = strtoupper(substr(uniqid(), -6));
            $code = sprintf(
                'CERT-%s-%d-%d-%s-%s',
                $timestamp,
                $userId,
                $courseId,
                $levelId ?? 0,
                $random
            );
        } while (Certificate::where('certificate_code', $code)->exists());

        return $code;
    }

    /**
     * Build certificate payload in English-script style.
     * Names and course titles are romanized; level text is generated from level number.
     */
    public function buildEnglishCertificatePayload(Certificate $certificate): array
    {
        $certificate->load(['user', 'course', 'level']);

        $firstName = $certificate->user->first_name ?? '';
        $lastName = $certificate->user->last_name ?? '';
        $fullNameAr = trim($firstName . ' ' . $lastName);
        $recipientNameEn = $fullNameAr !== '' ? CertificateEnglishHelper::romanizeArabicName($fullNameAr) : '—';

        $courseTitleAr = $certificate->course->title ?? '';
        $courseTitleEn = $courseTitleAr !== '' ? CertificateEnglishHelper::translateToEnglish($courseTitleAr) : '—';

        $level = $certificate->level;
        $levelTitleEn = null;
        $levelNumber = null;
        if ($level) {
            $levelNumber = $level->level_number ?? null;
            $levelTitleEn = CertificateEnglishHelper::levelLabelFromNumber($levelNumber);
        }

        $isLevelCertificate = $certificate->level_id !== null;
        $completionStatement = $this->getCompletionStatement($certificate);
        $programDisplay = $this->getProgramDisplayLine($certificate, $courseTitleEn, $levelTitleEn, $levelNumber);

        $issuedDateEn = CertificateEnglishHelper::formatDateInEnglish($certificate->issued_at);
        $hours = $this->calculateTrainingHours($certificate);
        $hoursTextEn = CertificateEnglishHelper::numberToEnglishWords($hours);

        $trainingProvider = config('certificate.training_provider_default', 'Diplomasi');
        $examProvider = config('certificate.exam_provider_default', 'Diplomasi');

        $templatePath = public_path(self::TEMPLATE_PATH);
        if (!file_exists($templatePath)) {
            $templatePath = null;
        }
        $templateImageDataUri = $this->buildDataUriFromPath($templatePath);

        $showAppLogo = (bool) config('certificate.show_app_logo', true);
        $appLogoPath = public_path(ltrim((string) config('certificate.app_logo_path', 'images/logo.png'), '/'));
        $appLogoDataUri = ($showAppLogo && $appLogoPath && file_exists($appLogoPath)) ? $this->buildDataUriFromPath($appLogoPath) : null;

        return [
            'recipient_name_en' => $recipientNameEn,
            'course_title_en' => $courseTitleEn,
            'level_title_en' => $levelTitleEn,
            'level_number' => $levelNumber,
            'is_level_certificate' => $isLevelCertificate,
            'completion_statement' => $completionStatement,
            'program_display' => $programDisplay,
            'issued_date_en' => $issuedDateEn,
            'certificate_code' => $certificate->certificate_code ?? '—',
            'training_provider' => $trainingProvider,
            'exam_provider' => $examProvider,
            'hours' => $hours,
            'hours_text_en' => $hoursTextEn,
            'template_image_path' => $templatePath,
            'template_image_data_uri' => $templateImageDataUri,
            'show_app_logo' => $showAppLogo,
            'app_logo_data_uri' => $appLogoDataUri,
        ];
    }

    /**
     * Dynamic completion wording: level vs full course.
     */
    private function getCompletionStatement(Certificate $certificate): string
    {
        if ($certificate->level_id !== null) {
            return 'has successfully completed the following level';
        }
        return 'has successfully completed the full programme';
    }

    /**
     * Line under "HAS COMPLETED": specific level within course, or full course title.
     */
    private function getProgramDisplayLine(Certificate $certificate, string $courseTitleEn, ?string $levelTitleEn, $levelNumber): string
    {
        if ($certificate->level_id !== null && ($levelTitleEn !== null || $levelNumber !== null)) {
            $levelPart = $levelTitleEn ?: (($levelNumber !== null) ? ("Level " . $levelNumber) : '');
            return $levelPart !== '' ? "{$levelPart} — {$courseTitleEn}" : $courseTitleEn;
        }
        return $courseTitleEn;
    }

    /**
     * توليد PDF الشهادة مباشرة للعرض في المتصفح (الطريقة الجديدة - أفضل للعربية)
     */
    public function generateCertificatePdf(Certificate $certificate): \Illuminate\Http\Response
    {
        try {
            $certificate->load(['user', 'course', 'level']);

            $payload = $this->buildEnglishCertificatePayload($certificate);
            $qrCodePath = null;
            if ($certificate->qr_code) {
                $qrCodeFullPath = storage_path('app/public/' . $certificate->qr_code);
                if (file_exists($qrCodeFullPath)) {
                    $qrCodePath = $qrCodeFullPath;
                }
            }
            $payload['qr_code_path'] = $qrCodePath;
            $payload['qr_code_data_uri'] = $this->buildDataUriFromPath($qrCodePath);

            $html = View::make('certificates.image_template', $payload)->render();

            Log::info("Generated HTML from Blade template for PDF", [
                'certificate_id' => $certificate->id,
                'recipient_name_en' => $payload['recipient_name_en'],
            ]);

            // إنشاء PDF باستخدام mPDF
            $fontPath = storage_path('app/fonts/itfHuwiyaDisplay-Regular.otf');
            $fontDir = storage_path('app/fonts');

            // الحصول على الإعدادات الافتراضية لـ mPDF
            $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];

            $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            // إعداد fontdata - استخدام dejavusans فقط (يدعم العربية بشكل ممتاز)
            // ملاحظة: mPDF لا يدعم OTF fonts مع PostScript outlines
            $customFontData = $fontData;

            $mpdf = new Mpdf([
                'tempDir' => storage_path('framework/cache'),
                'mode' => 'utf-8',
                'format' => 'A4-L', // A4 بالعرض (landscape)
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
                'default_font' => 'dejavusans',
                'default_font_size' => 12,
                'fontDir' => array_merge($fontDirs, [$fontDir]),
                'fontdata' => $customFontData,
                'useOTL' => 0xFF,
                'useKashida' => 75,
                'shrink_tables_to_fit' => 1,
                'use_kwt' => true,
                'keepColumns' => true,
                'keep_table_proportions' => true,
                'dpi' => 96,
            ]);

            $mpdf->SetDirectionality('ltr');
            $mpdf->SetTitle("Certificate - {$certificate->certificate_code}");
            $mpdf->SetAuthor('Diplomasi');
            $mpdf->SetCreator('Diplomasi Certificate System');

            // كتابة HTML إلى PDF
            $mpdf->WriteHTML($html);

            // إرجاع PDF مباشرة للعرض في المتصفح
            $pdfContent = $mpdf->Output('', 'S');

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="certificate_' . $certificate->certificate_code . '.pdf"');
        } catch (MpdfException $e) {
            Log::error("Error generating certificate PDF (mPDF)", [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception(trans('messages.certificate.pdf_generation_failed') . ': ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Error generating certificate PDF", [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception(trans('messages.certificate.pdf_generation_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * توليد PNG من PDF (للعرض كصورة - اختياري)
     * هذه المرة نستخدم PDF الذي تم إنشاؤه بـ mPDF (يدعم العربية بشكل ممتاز)
     */
    public function generateCertificateImageFromPdf(Certificate $certificate): ?string
    {
        try {
            $certificate->load(['user', 'course', 'level']);

            $payload = $this->buildEnglishCertificatePayload($certificate);
            $qrCodePath = null;
            if ($certificate->qr_code) {
                $qrCodeFullPath = storage_path('app/public/' . $certificate->qr_code);
                if (file_exists($qrCodeFullPath)) {
                    $qrCodePath = $qrCodeFullPath;
                }
            }
            $payload['qr_code_path'] = $qrCodePath;
            $payload['qr_code_data_uri'] = $this->buildDataUriFromPath($qrCodePath);

            $html = View::make('certificates.image_template', $payload)->render();

            // إنشاء PDF باستخدام mPDF
            $fontPath = storage_path('app/fonts/itfHuwiyaDisplay-Regular.otf');
            $fontDir = storage_path('app/fonts');

            // الحصول على الإعدادات الافتراضية لـ mPDF
            $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];

            $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            // إعداد fontdata - استخدام dejavusans فقط (يدعم العربية بشكل ممتاز)
            // ملاحظة: mPDF لا يدعم OTF fonts مع PostScript outlines
            $customFontData = $fontData;

            $mpdf = new Mpdf([
                'tempDir' => storage_path('framework/cache'),
                'mode' => 'utf-8',
                'format' => 'A4-L', // A4 Landscape
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
                'default_font' => 'dejavusans',
                'default_font_size' => 12,
                'fontDir' => array_merge($fontDirs, [$fontDir]),
                'fontdata' => $customFontData,
                'useOTL' => 0xFF,
                'useKashida' => 75,
                'dpi' => 96,
            ]);

            $mpdf->SetDirectionality('ltr');
            $mpdf->WriteHTML($html);

            // حفظ PDF مؤقتاً
            $tempPdfPath = storage_path('app/temp/certificate_' . $certificate->id . '_' . time() . '.pdf');
            $tempDir = dirname($tempPdfPath);
            if (!File::exists($tempDir)) {
                File::makeDirectory($tempDir, 0755, true);
            }
            $mpdf->Output($tempPdfPath, 'F');

            // محاولة تحويل PDF إلى PNG
            // أولاً: Ghostscript (أخف وأسرع)
            // ثانياً: Imagick (إذا فشل Ghostscript)
            try {
                // محاولة استخدام Ghostscript أولاً (أخف وأسرع)
                $gsPath = $this->findGhostscriptPath();
                if ($gsPath && $this->convertPdfToPngWithGhostscript($tempPdfPath, $certificate, $gsPath)) {
                    $imagePath = 'certificates/' . $certificate->certificate_code . '.png';
                    if (file_exists(storage_path('app/public/' . $imagePath))) {
                        Log::info("Converted PDF to PNG using Ghostscript", [
                            'certificate_id' => $certificate->id,
                            'image_path' => $imagePath,
                        ]);
                        return $imagePath;
                    }
                }

                // إذا فشل Ghostscript، استخدم Imagick أو GD كبديل
                if (!extension_loaded('imagick') || !class_exists('\Imagick')) {
                    Log::warning("Imagick not available, Ghostscript also failed, trying GD fallback");
                    return $this->tryGenerateCertificateImageWithGd($certificate);
                }

                // Imagick class is available via PHP extension (checked above)
                // Using variable to avoid static analyzer warnings for extension class
                $imagickClass = 'Imagick';
                call_user_func([$imagickClass, 'setResourceLimit'], constant('Imagick::RESOURCETYPE_MEMORY'), 256); // تقليل الذاكرة
                call_user_func([$imagickClass, 'setResourceLimit'], constant('Imagick::RESOURCETYPE_MAP'), 512);
                call_user_func([$imagickClass, 'setResourceLimit'], constant('Imagick::RESOURCETYPE_AREA'), 64);
                call_user_func([$imagickClass, 'setResourceLimit'], constant('Imagick::RESOURCETYPE_DISK'), 1024);
                call_user_func([$imagickClass, 'setResourceLimit'], constant('Imagick::RESOURCETYPE_FILE'), 384);
                call_user_func([$imagickClass, 'setResourceLimit'], constant('Imagick::RESOURCETYPE_TIME'), 30); // 30 seconds max

                $imagick = new $imagickClass();
                $imagick->setResolution(200, 200); // keep enough detail for QR readability
                $imagick->readImage($tempPdfPath . '[0]');
                $imagick->setImageFormat('png');
                $imagickPixelClass = 'ImagickPixel';
                $imagick->setImageBackgroundColor(new $imagickPixelClass('white'));
                $imagick = $imagick->mergeImageLayers(constant('Imagick::LAYERMETHOD_FLATTEN'));

                // تحسين الصورة لتقليل الحجم
                $imagick->stripImage();
                $imagick->setImageCompressionQuality(95);

                // حفظ PNG
                $imagePath = 'certificates/' . $certificate->certificate_code . '.png';
                $fullImagePath = storage_path('app/public/' . $imagePath);
                $imageDir = dirname($fullImagePath);
                if (!File::exists($imageDir)) {
                    File::makeDirectory($imageDir, 0755, true);
                }

                $imagick->writeImage($fullImagePath);
                $imagick->destroy();

                Log::info("Converted PDF to PNG successfully", [
                    'certificate_id' => $certificate->id,
                    'image_path' => $imagePath,
                ]);

                return $imagePath;
            } catch (\Exception $e) {
                // إذا كان الخطأ من نوع ImagickException، نتعامل معه بشكل خاص
                $imagickExceptionClass = 'ImagickException';
                $isImagickException = class_exists($imagickExceptionClass) &&
                    (get_class($e) === $imagickExceptionClass || str_contains(get_class($e), 'ImagickException'));

                if ($isImagickException) {
                    // إذا فشل Imagick، لا مشكلة - PDF يعمل بشكل ممتاز
                    Log::warning("Failed to convert PDF to PNG (optional)", [
                        'certificate_id' => $certificate->id,
                        'error' => $e->getMessage(),
                        'note' => 'PDF is available and works perfectly',
                    ]);
                    return null;
                }

                // إذا كان خطأ آخر، نعيده
                Log::warning("Failed to convert PDF to PNG (optional)", [
                    'certificate_id' => $certificate->id,
                    'error' => $e->getMessage(),
                    'note' => 'PDF is available and works perfectly',
                ]);
                return null;
            } finally {
                // حذف PDF المؤقت
                if (File::exists($tempPdfPath)) {
                    File::delete($tempPdfPath);
                }
            }
        } catch (\Exception $e) {
            // PNG اختياري - إذا فشل، PDF يعمل بشكل ممتاز
            Log::warning("Error converting PDF to PNG (optional)", [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
                'note' => 'PDF is available and works perfectly - PNG is optional',
            ]);
            return null;
        }
    }

    /**
     * Fallback: توليد PNG بالاعتماد على GD فقط (عند عدم توفر Ghostscript و Imagick)
     */
    private function tryGenerateCertificateImageWithGd(Certificate $certificate): ?string
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
            Log::warning("GD extension not available; cannot generate PNG fallback", [
                'certificate_id' => $certificate->id,
            ]);
            return null;
        }

        try {
            $certificate->load(['user', 'course', 'level']);
            $payload = $this->buildEnglishCertificatePayload($certificate);

            $templatePath = public_path(self::TEMPLATE_PATH);
            if (!file_exists($templatePath)) {
                $templatePath = public_path('images/certificate-template.jpg');
            }
            if (!file_exists($templatePath)) {
                $templatePath = public_path('images/certificate-template.png');
            }

            $img = null;
            if ($templatePath && file_exists($templatePath)) {
                $ext = strtolower(pathinfo($templatePath, PATHINFO_EXTENSION));
                $img = ($ext === 'jpg' || $ext === 'jpeg') ? @imagecreatefromjpeg($templatePath) : @imagecreatefrompng($templatePath);
            }
            /* A4 بالعرض: نسبة 297:210 */
            if (!$img) {
                $img = imagecreatetruecolor(1400, 990);
                if ($img) {
                    imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
                }
            }
            if (!$img) {
                return null;
            }

            $w = imagesx($img);
            $h = imagesy($img);
            $black = imagecolorallocate($img, 30, 30, 50);
            $blue = imagecolorallocate($img, 30, 58, 95);
            $white = imagecolorallocate($img, 255, 255, 255);

            /* شعار التطبيق بالزاوية اليمنى دون التأثير على الشكل */
            if (!empty($payload['show_app_logo']) && !empty($payload['app_logo_data_uri'])) {
                $dataUri = $payload['app_logo_data_uri'];
                $base64 = preg_match('/^data:\w+\/\w+;base64,(.+)$/', $dataUri, $m) ? ($m[1] ?? '') : '';
                if ($base64 !== '') {
                    $bin = @base64_decode($base64, true);
                    if ($bin !== false) {
                        $logo = @imagecreatefromstring($bin);
                        if ($logo) {
                            $lw = imagesx($logo);
                            $lh = imagesy($logo);
                            $targetW = (int) min(120, $w * 0.10);
                            $targetH = (int) max(1, round($lh * ($targetW / max(1, $lw))));
                            $logoX = $w - $targetW - 50;
                            $logoY = 40;
                            imagecopyresampled($img, $logo, $logoX, $logoY, 0, 0, $targetW, $targetH, $lw, $lh);
                            imagedestroy($logo);
                        }
                    }
                }
            }

            $cx = (int)($w / 2);
            $font = 5;
            $lineH = imagefontheight($font);
            $y = (int)($h * 0.12);

            imagestring($img, $font, (int)($cx - 4.5 * imagefontwidth($font) * 9), $y, 'This document certifies that', $black);
            $y += $lineH + 8;
            $name = $payload['recipient_name_en'] ?? '—';
            $nameW = imagefontwidth($font) * strlen($name);
            imagestring($img, $font, (int)($cx - $nameW / 2), $y, $name, $black);
            $y += $lineH + 10;

            $badgeW = 180;
            $badgeH = 28;
            imagefilledrectangle($img, (int)($cx - $badgeW / 2), $y, (int)($cx + $badgeW / 2), $y + $badgeH, $blue);
            imagestring($img, $font, (int)($cx - 4.5 * imagefontwidth($font) * 6), $y + 6, 'HAS COMPLETED', $white);
            $y += $badgeH + 8;

            $stmt = $payload['completion_statement'] ?? '';
            $stmtW = imagefontwidth($font) * strlen($stmt);
            imagestring($img, $font, (int)($cx - $stmtW / 2), $y, $stmt, $black);
            $y += $lineH + 6;
            $prog = $payload['program_display'] ?? '—';
            $progW = imagefontwidth($font) * strlen($prog);
            imagestring($img, $font, (int)($cx - $progW / 2), $y, $prog, $black);

            $left = (int)($w * 0.05);
            $bottomY = (int)($h * 0.78);
            imagestring($img, $font, $left, $bottomY, 'DATE ' . ($payload['issued_date_en'] ?? '—'), $black);
            imagestring($img, $font, $left, $bottomY + $lineH + 4, 'NO. ' . ($payload['certificate_code'] ?? '—'), $black);
            imagestring($img, $font, $left, $bottomY + 2 * ($lineH + 4), 'Training Provider: ' . ($payload['training_provider'] ?? 'Diplomasi'), $black);
            imagestring($img, $font, $left, $bottomY + 3 * ($lineH + 4), 'Exam Provider: ' . ($payload['exam_provider'] ?? 'Diplomasi'), $black);

            if ($certificate->qr_code) {
                $qrPath = storage_path('app/public/' . $certificate->qr_code);
                if (file_exists($qrPath)) {
                    $ext = strtolower(pathinfo($qrPath, PATHINFO_EXTENSION));
                    $qrImg = ($ext === 'png') ? @imagecreatefrompng($qrPath) : @imagecreatefromjpeg($qrPath);
                    if ($qrImg) {
                        $qrW = imagesx($qrImg);
                        $qrH = imagesy($qrImg);
                        $sz = min(120, (int)($h * 0.18));
                        $qx = (int)($w - $sz - 40);
                        $qy = (int)($h - $sz - 40);
                        imagecopyresampled($img, $qrImg, $qx, $qy, 0, 0, $sz, $sz, $qrW, $qrH);
                        imagedestroy($qrImg);
                    }
                }
            }

            $outDir = storage_path('app/public/certificates');
            if (!File::isDirectory($outDir)) {
                File::makeDirectory($outDir, 0755, true);
            }
            $outPath = $outDir . '/' . $certificate->certificate_code . '.png';
            imagepng($img, $outPath, 6);
            imagedestroy($img);

            if (file_exists($outPath)) {
                Log::info("Certificate PNG generated via GD fallback", [
                    'certificate_id' => $certificate->id,
                    'path' => 'certificates/' . $certificate->certificate_code . '.png',
                ]);
                return 'certificates/' . $certificate->certificate_code . '.png';
            }
            return null;
        } catch (\Throwable $e) {
            Log::warning("GD fallback certificate image failed", [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * توليد صورة الشهادة من Blade template (الطريقة القديمة - محفوظة للرجوع)
     * ملاحظة: PNG اختياري - إذا فشل، PDF يعمل بشكل ممتاز
     */
    public function generateCertificateImageFromBlade(Certificate $certificate): ?string
    {
        try {
            // تحميل العلاقات المطلوبة
            $certificate->load(['user', 'course', 'level']);

            // تجهيز البيانات للـ Blade template
            $userName = trim($certificate->user->first_name . ' ' . $certificate->user->last_name);
            $courseTitle = $certificate->course->title ?? '';
            $hours = $this->calculateTrainingHours($certificate);
            $hoursText = $this->numberToArabicWords($hours);
            $date = $this->formatDateInArabic($certificate->issued_at);

            // مسار QR Code
            $qrCodePath = null;
            if ($certificate->qr_code) {
                $qrCodeFullPath = storage_path('app/public/' . $certificate->qr_code);
                if (file_exists($qrCodeFullPath)) {
                    $qrCodePath = $qrCodeFullPath;
                }
            }

            // إنشاء HTML من Blade template
            $html = View::make('certificates.image_template', [
                'user_name' => $userName,
                'course_title' => $courseTitle,
                'hours' => $hours,
                'hours_text' => $hoursText,
                'date' => $date,
                'qr_code_path' => $qrCodePath,
            ])->render();

            Log::info("Generated HTML from Blade template", [
                'certificate_id' => $certificate->id,
                'user_name' => $userName,
            ]);

            // إنشاء PDF باستخدام mPDF
            $fontPath = storage_path('app/fonts/itfHuwiyaDisplay-Regular.otf');
            $fontDir = storage_path('app/fonts');

            // الحصول على الإعدادات الافتراضية لـ mPDF
            $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];

            $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            // إعداد fontdata - استخدام dejavusans فقط (يدعم العربية بشكل ممتاز)
            // ملاحظة: mPDF لا يدعم OTF fonts مع PostScript outlines
            $customFontData = $fontData;

            $mpdf = new Mpdf([
                'tempDir' => storage_path('framework/cache'),
                'mode' => 'utf-8',
                'format' => 'A4', // A4 Portrait to match certificate template
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
                'default_font' => 'dejavusans', // dejavusans يدعم العربية بشكل ممتاز
                'default_font_size' => 12,
                'fontDir' => array_merge($fontDirs, [$fontDir]),
                'fontdata' => $customFontData,
                'useOTL' => 0xFF,
                'useKashida' => 75,
                'shrink_tables_to_fit' => 1,
                'use_kwt' => true,
                'keepColumns' => true,
                'keep_table_proportions' => true,
                'dpi' => 96,
            ]);

            // إعداد mPDF للعربية
            $mpdf->SetDirectionality('rtl');
            $mpdf->SetTitle("شهادة - {$certificate->certificate_code}");
            $mpdf->SetAuthor('Diplomasi');
            $mpdf->SetCreator('Diplomasi Certificate System');

            Log::info("Using dejavusans font for Arabic support (generateCertificateImageFromBlade)", [
                'note' => 'dejavusans has excellent Arabic support built-in',
            ]);

            // كتابة HTML إلى PDF
            $mpdf->WriteHTML($html);

            // حفظ PDF مؤقتاً
            $tempPdfPath = storage_path('app/temp/certificate_' . $certificate->id . '_' . time() . '.pdf');
            $tempDir = dirname($tempPdfPath);
            if (!File::exists($tempDir)) {
                File::makeDirectory($tempDir, 0755, true);
            }

            $mpdf->Output($tempPdfPath, 'F');

            Log::info("Generated PDF from HTML", [
                'certificate_id' => $certificate->id,
                'pdf_path' => $tempPdfPath,
            ]);

            // محاولة تحويل PDF إلى PNG
            // أولاً: Ghostscript (أخف وأسرع)
            // ثانياً: Imagick (إذا فشل Ghostscript)
            try {
                // محاولة استخدام Ghostscript أولاً (أخف وأسرع)
                $gsPath = $this->findGhostscriptPath();
                if ($gsPath && $this->convertPdfToPngWithGhostscript($tempPdfPath, $certificate, $gsPath)) {
                    $imagePath = 'certificates/' . $certificate->certificate_code . '.png';
                    if (file_exists(storage_path('app/public/' . $imagePath))) {
                        Log::info("Converted PDF to PNG using Ghostscript (generateCertificateImageFromBlade)", [
                            'certificate_id' => $certificate->id,
                            'image_path' => $imagePath,
                        ]);
                        return $imagePath;
                    }
                }

                // إذا فشل Ghostscript، استخدم Imagick
                if (!extension_loaded('imagick') || !class_exists('\Imagick')) {
                    Log::warning("Imagick not available, Ghostscript also failed");
                    return null;
                }

                // Imagick class is available via PHP extension (checked above)
                $imagickClass = 'Imagick';
                call_user_func([$imagickClass, 'setResourceLimit'], constant('Imagick::RESOURCETYPE_MEMORY'), 256);
                call_user_func([$imagickClass, 'setResourceLimit'], constant('Imagick::RESOURCETYPE_MAP'), 512);
                call_user_func([$imagickClass, 'setResourceLimit'], constant('Imagick::RESOURCETYPE_AREA'), 64);
                call_user_func([$imagickClass, 'setResourceLimit'], constant('Imagick::RESOURCETYPE_DISK'), 1024);
                call_user_func([$imagickClass, 'setResourceLimit'], constant('Imagick::RESOURCETYPE_FILE'), 384);
                call_user_func([$imagickClass, 'setResourceLimit'], constant('Imagick::RESOURCETYPE_TIME'), 30);

                $imagick = new $imagickClass();
                $imagick->setResolution(50, 50); // دقة منخفضة جداً
                $imagick->readImage($tempPdfPath . '[0]');
                $imagick->setImageFormat('png');
                $imagickPixelClass = 'ImagickPixel';
                $imagick->setImageBackgroundColor(new $imagickPixelClass('white'));
                $imagick = $imagick->mergeImageLayers(constant('Imagick::LAYERMETHOD_FLATTEN'));
                $imagick->stripImage();
                $imagick->setImageCompressionQuality(75);

                // حفظ PNG
                $imagePath = 'certificates/' . $certificate->certificate_code . '.png';
                $fullImagePath = storage_path('app/public/' . $imagePath);
                $imageDir = dirname($fullImagePath);
                if (!File::exists($imageDir)) {
                    File::makeDirectory($imageDir, 0755, true);
                }

                $imagick->writeImage($fullImagePath);
                $imagick->destroy();

                Log::info("Converted PDF to PNG", [
                    'certificate_id' => $certificate->id,
                    'image_path' => $imagePath,
                ]);

                return $imagePath;
            } catch (\Exception $e) {
                Log::warning("Failed to convert PDF to PNG (optional)", [
                    'certificate_id' => $certificate->id,
                    'error' => $e->getMessage(),
                    'note' => 'PDF is available and works perfectly',
                ]);
                return null;
            } finally {
                // حذف PDF المؤقت
                if (File::exists($tempPdfPath)) {
                    File::delete($tempPdfPath);
                }
            }
        } catch (MpdfException $e) {
            Log::error("Error generating certificate image from Blade (mPDF)", [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
            ]);
            return null; // PNG اختياري
        } catch (\Exception $e) {
            Log::error("Error generating certificate image from Blade", [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null; // PNG اختياري
        }
    }

    /**
     * البحث عن مسار Ghostscript (يدعم Windows و Linux)
     */
    private function findGhostscriptPath(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = [];
            @exec('where gswin64c 2>nul', $output, $returnVar);
            if ($returnVar === 0 && !empty($output)) {
                return trim($output[0]);
            }
            $output = [];
            @exec('where gswin32c 2>nul', $output, $returnVar);
            if ($returnVar === 0 && !empty($output)) {
                return trim($output[0]);
            }
            $output = [];
            @exec('where gs 2>nul', $output, $returnVar);
            if ($returnVar === 0 && !empty($output)) {
                return trim($output[0]);
            }
            return null;
        }

        $possiblePaths = [
            '/usr/bin/gs',
            '/usr/local/bin/gs',
            '/bin/gs',
            'gs',
        ];

        foreach ($possiblePaths as $path) {
            if ($path === 'gs') {
                $output = [];
                $returnVar = 0;
                @exec('which gs 2>/dev/null', $output, $returnVar);
                if ($returnVar === 0 && !empty($output)) {
                    return trim($output[0]);
                }
            } else {
                if (file_exists($path) && is_executable($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * تحويل PDF إلى PNG باستخدام Ghostscript (أخف من Imagick)
     */
    private function convertPdfToPngWithGhostscript(string $pdfPath, Certificate $certificate, string $gsPath): bool
    {
        try {
            $imagePath = 'certificates/' . $certificate->certificate_code . '.png';
            $fullImagePath = storage_path('app/public/' . $imagePath);
            $imageDir = dirname($fullImagePath);
            if (!File::exists($imageDir)) {
                File::makeDirectory($imageDir, 0755, true);
            }

            // استخدام Ghostscript لتحويل PDF إلى PNG
            // -dNOPAUSE: لا توقف بين الصفحات
            // -dBATCH: إنهاء بعد المعالجة
            // -sDEVICE=png16m: استخدام PNG 24-bit
            // -r300: higher output resolution for better QR readability
            // -dFirstPage=1 -dLastPage=1: الصفحة الأولى فقط
            // -sOutputFile: ملف الإخراج
            $command = escapeshellarg($gsPath) .
                ' -dNOPAUSE -dBATCH -sDEVICE=png16m' .
                ' -r300' .
                ' -dFirstPage=1 -dLastPage=1' .
                ' -sOutputFile=' . escapeshellarg($fullImagePath) .
                ' ' . escapeshellarg($pdfPath) .
                ' 2>&1';

            $output = [];
            $returnVar = 0;
            @exec($command, $output, $returnVar);

            if ($returnVar === 0 && file_exists($fullImagePath) && filesize($fullImagePath) > 0) {
                return true;
            }

            Log::warning("Ghostscript conversion failed", [
                'command' => $command,
                'output' => implode("\n", $output),
                'return_var' => $returnVar,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::warning("Ghostscript conversion error", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * توليد صورة الشهادة من القالب مع Text Overlay (الطريقة القديمة - محفوظة للرجوع)
     */
    public function generateCertificateImage(Certificate $certificate): string
    {
        try {
            // محاولة استخدام Imagick أولاً (يدعم العربية بشكل أفضل)
            // إذا لم يكن متاحاً، نستخدم GD
            $useImagick = extension_loaded('imagick');

            if ($useImagick) {
                try {
                    $manager = new ImageManager(new ImagickDriver());
                    Log::info("Using Imagick driver for Arabic text support");
                } catch (\Exception $e) {
                    Log::warning("Imagick driver failed, falling back to GD", ['error' => $e->getMessage()]);
                    $useImagick = false;
                    $manager = new ImageManager(new GdDriver());
                }
            } else {
                $manager = new ImageManager(new GdDriver());
                Log::warning("Imagick extension not available, using GD driver (limited Arabic support)");
            }

            // إنشاء صورة بيضاء جديدة بدلاً من استخدام القالب
            // حجم مناسب للطباعة: 1200x850 بكسل (نسبة 16:11 تقريباً)
            $imageWidth = 1200;
            $imageHeight = 850;

            // إنشاء صورة بيضاء جديدة
            $image = $manager->create($imageWidth, $imageHeight);

            // رسم خلفية بيضاء
            $image->fill('#FFFFFF');

            Log::info("Certificate image created (white background)", [
                'image_width' => $imageWidth,
                'image_height' => $imageHeight,
                'driver' => $useImagick ? 'Imagick' : 'GD',
            ]);

            // تحميل العلاقات المطلوبة
            $certificate->load(['user', 'course', 'level']);

            // تحديد المواضع بناءً على الأبعاد الجديدة
            $centerX = $imageWidth / 2;

            // استخدام الخط العربي الموجود: itfHuwiyaDisplay-Regular.otf
            $arabicFontPath = storage_path('app/fonts/itfHuwiyaDisplay-Regular.otf');
            if (!file_exists($arabicFontPath)) {
                Log::warning("Arabic font not found, using default font", [
                    'expected_path' => $arabicFontPath,
                ]);
                $arabicFontPath = null;
            } else {
                Log::info("Arabic font loaded successfully", [
                    'font_path' => $arabicFontPath,
                ]);
            }

            // بناء نص الشهادة - نص عربي موصول بشكل احترافي
            $userName = trim($certificate->user->first_name . ' ' . $certificate->user->last_name);

            // دالة مساعدة لكتابة النص العربي بشكل احترافي
            // استخدام Imagick مباشرة إذا كان متاحاً
            $writeArabicText = function ($text, $x, $y, $fontSize, $color) use (&$image, $arabicFontPath, $manager, $useImagick) {
                // معالجة النص العربي للتأكد من الاتجاه الصحيح
                $processedText = ArabicTextRenderer::prepareArabicText($text);

                // استخدام خدمة عرض النص العربي
                $image = ArabicTextRenderer::writeArabicText(
                    $image,
                    $processedText,
                    $x,
                    $y,
                    $fontSize,
                    $color,
                    $arabicFontPath ?? '',
                    $manager
                );
            };

            // 1. كتابة نص "تمنح هذه الشهادة الى:" ثم اسم المستخدم
            if (!empty($userName)) {
                // نص "تمنح هذه الشهادة الى:"
                $awardText = "تمنح هذه الشهادة الى:";
                $awardTextY = (int)($imageHeight * 0.30);
                $writeArabicText($awardText, $centerX, $awardTextY, 32, '#1a1a5e');

                // اسم المستخدم (بخط أكبر)
                $userNameY = (int)($imageHeight * 0.40);
                $writeArabicText($userName, $centerX, $userNameY, 60, '#1a1a5e');
            }

            // 2. كتابة نص "وذلك لحضوره / ها الدورة التدريبية بعنوان:" ثم اسم الكورس
            $courseTitle = $certificate->course->title;
            if (!empty($courseTitle)) {
                // نص "وذلك لحضوره / ها الدورة التدريبية بعنوان:"
                $attendanceText = "وذلك لحضوره / ها الدورة التدريبية بعنوان:";
                $attendanceTextY = (int)($imageHeight * 0.52);
                $writeArabicText($attendanceText, $centerX, $attendanceTextY, 28, '#1a1a5e');

                // اسم الكورس (بخط أكبر ولون ذهبي/بني برتقالي)
                $courseTitleY = (int)($imageHeight * 0.60);
                $writeArabicText($courseTitle, $centerX, $courseTitleY, 48, '#D4A017');

                // نص "التي اقامتها شركة دبلوماسي - diplomasi وذلك ضمن برامجها وفعالياتها الريادية"
                $companyText = "التي اقامتها شركة دبلوماسي - diplomasi وذلك ضمن برامجها وفعالياتها الريادية";
                $companyTextY = (int)($imageHeight * 0.68);
                $writeArabicText($companyText, $centerX, $companyTextY, 22, '#1a1a5e');
            }

            // 3. كتابة مدة التدريب: "بمدة تدريبية قدرها [عدد بالعربي] ([عدد]) ساعة تدريبية"
            $hours = $this->calculateTrainingHours($certificate);
            $hoursText = $this->numberToArabicWords($hours);
            $trainingDuration = "بمدة تدريبية قدرها {$hoursText} ({$hours}) ساعة تدريبية";
            $trainingDurationY = (int)($imageHeight * 0.75);
            $writeArabicText($trainingDuration, $centerX, $trainingDurationY, 26, '#1a1a5e');

            // 4. كتابة التاريخ في الأسفل الأيمن
            $date = $this->formatDateInArabic($certificate->issued_at);
            $dateText = "التاريخ: {$date}";
            $dateX = (int)($imageWidth * 0.70);
            $dateY = (int)($imageHeight * 0.88);
            $writeArabicText($dateText, $dateX, $dateY, 20, '#1a1a5e');

            // 5. دمج QR Code في الصورة (إذا كان موجوداً)
            if ($certificate->qr_code) {
                $qrCodePath = storage_path('app/public/' . $certificate->qr_code);
                if (file_exists($qrCodePath)) {
                    try {
                        // إذا كان QR Code بصيغة SVG، نحوله إلى PNG أولاً
                        if (str_ends_with($certificate->qr_code, '.svg')) {
                            // قراءة SVG وتحويله إلى PNG
                            // SVG لا يمكن قراءته مباشرة بـ GD driver، لذلك سنحاول قراءته كـ image
                            // أو يمكن تخطي دمج QR Code في الصورة إذا كان SVG
                            // حالياً سنتخطي دمج SVG (يمكن عرضه بشكل منفصل في الواجهة)
                            Log::info("QR Code is SVG format, skipping merge into certificate image", [
                                'certificate_id' => $certificate->id,
                                'qr_code_path' => $qrCodePath,
                            ]);
                        } else {
                            // إذا كان PNG، دمجها مباشرة
                            $qrCodeImage = $manager->read($qrCodePath);
                            $qrCodeImage->resize(200, 200);
                            // وضع QR Code في الزاوية اليمنى السفلى
                            $image->place($qrCodeImage, 'bottom-right', 50, 50);
                        }
                    } catch (\Exception $e) {
                        Log::warning("Failed to merge QR code into certificate image", [
                            'certificate_id' => $certificate->id,
                            'qr_code_path' => $qrCodePath,
                            'error' => $e->getMessage(),
                        ]);
                        // تجاهل الخطأ والمتابعة بدون QR Code في الصورة
                    }
                }
            }

            // حفظ الصورة النهائية
            $outputFolder = 'certificates';
            $outputPath = storage_path("app/public/{$outputFolder}");
            if (!File::isDirectory($outputPath)) {
                File::makeDirectory($outputPath, 0755, true, true);
            }

            $outputPath = storage_path("app/public/{$outputFolder}/{$certificate->certificate_code}.png");
            $image->toPng()->save($outputPath);

            // التحقق من أن الملف تم حفظه بنجاح
            if (!file_exists($outputPath)) {
                throw new \Exception(trans('messages.certificate.image_save_failed') . ": {$outputPath}");
            }

            Log::info("Certificate image generated successfully", [
                'certificate_id' => $certificate->id,
                'certificate_code' => $certificate->certificate_code,
                'image_path' => $outputPath,
                'file_size' => filesize($outputPath),
            ]);

            return "{$outputFolder}/{$certificate->certificate_code}.png";
        } catch (\Throwable $e) {
            // التقاط جميع أنواع الأخطاء (Exception و Error)
            Log::error("Error generating certificate image", [
                'certificate_id' => $certificate->id ?? null,
                'certificate_code' => $certificate->certificate_code ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception(trans('messages.certificate.image_generation_failed') . ': ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * توليد QR Code للشهادة
     */
    public function generateCertificateQrCode(Certificate $certificate): string
    {
        try {
            // استخدام web route لعرض صفحة الشهادة بدلاً من API endpoint
            // هذا يجعل QR Code يعرض صفحة ويب جميلة عند مسحه
            $verificationUrl = config('app.url') . '/certificates/verify/' . $certificate->certificate_code;

            $qrFolder = 'certificates/qr';
            $qrFolderPath = storage_path("app/public/{$qrFolder}");

            // إنشاء المجلد إذا لم يكن موجوداً
            if (!File::isDirectory($qrFolderPath)) {
                File::makeDirectory($qrFolderPath, 0755, true, true);
            }

            $qrCodePngPath = storage_path("app/public/{$qrFolder}/{$certificate->certificate_code}.png");
            $qrCodeSvgPath = storage_path("app/public/{$qrFolder}/{$certificate->certificate_code}.svg");

            $returnPath = null;
            try {
                // Preferred: PNG for stronger compatibility in rendered image/PDF
                QrCode::format('png')
                    ->size(520)
                    ->margin(2)
                    ->errorCorrection('H')
                    ->generate($verificationUrl, $qrCodePngPath);

                if (File::exists($qrCodePngPath)) {
                    $returnPath = "{$qrFolder}/{$certificate->certificate_code}.png";
                }
            } catch (\Throwable $pngError) {
                Log::warning("PNG QR generation failed, trying SVG fallback", [
                    'certificate_id' => $certificate->id,
                    'error' => $pngError->getMessage(),
                ]);
            }

            // Fallback: SVG
            if ($returnPath === null) {
                QrCode::format('svg')
                    ->size(520)
                    ->margin(2)
                    ->errorCorrection('H')
                    ->generate($verificationUrl, $qrCodeSvgPath);

                if (!File::exists($qrCodeSvgPath)) {
                    throw new \Exception(trans('messages.certificate.qr_code_svg_generation_failed'));
                }
                $returnPath = "{$qrFolder}/{$certificate->certificate_code}.svg";
            }

            // التحقق من أن الملف تم إنشاؤه بنجاح
            $fullGeneratedQr = storage_path('app/public/' . $returnPath);
            if (!File::exists($fullGeneratedQr)) {
                Log::error("Failed to generate QR code", [
                    'certificate_id' => $certificate->id,
                    'certificate_code' => $certificate->certificate_code,
                    'qr_code_path' => $fullGeneratedQr,
                ]);
                throw new \Exception(trans('messages.certificate.qr_code_generation_failed'));
            }

            Log::info("QR code generated successfully", [
                'certificate_id' => $certificate->id,
                'certificate_code' => $certificate->certificate_code,
                'qr_code_path' => $fullGeneratedQr,
                'format' => pathinfo($returnPath, PATHINFO_EXTENSION),
            ]);

            return $returnPath;
        } catch (\Exception $e) {
            Log::error("Error generating QR code", [
                'certificate_id' => $certificate->id,
                'certificate_code' => $certificate->certificate_code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * التحقق من صحة الشهادة
     */
    public function verifyCertificate(string $certificateCode)
    {
        $certificate = Certificate::where('certificate_code', $certificateCode)
            ->with(['user', 'course', 'level'])
            ->first();

        if (!$certificate) {
            return [
                'valid' => false,
                'message' => 'messages.certificate.not_found',
            ];
        }

        return [
            'valid' => true,
            'certificate' => $certificate,
            'user_name' => $certificate->user->first_name . ' ' . $certificate->user->last_name,
            'course_title' => $certificate->course->title,
            'level_title' => $certificate->level?->title,
            'issued_at' => $certificate->issued_at?->format('Y-m-d'),
        ];
    }

    /**
     * تحميل صورة الشهادة
     */
    public function downloadCertificateImage(int $id)
    {
        $certificate = $this->show($id);

        if (!$certificate->image_url) {
            MessageService::abort(404, 'messages.certificate.image_not_found');
        }

        $imagePath = storage_path('app/public/' . $certificate->image_url);

        if (!file_exists($imagePath)) {
            MessageService::abort(404, 'messages.certificate.image_file_not_found');
        }

        return response()->download($imagePath, $certificate->certificate_code . '.png');
    }

    /**
     * Build a data-uri from local file path for safe embedding in mPDF HTML.
     */
    private function buildDataUriFromPath(?string $path): ?string
    {
        if (!$path || !file_exists($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            default => null,
        };

        if ($mime === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false || $contents === '') {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    /**
     * حساب عدد ساعات التدريب
     */
    private function calculateTrainingHours(Certificate $certificate): int
    {
        // إذا كان المستوى محدداً، حساب ساعات المستوى
        if ($certificate->level_id) {
            $level = $certificate->level;
            if ($level) {
                // حساب عدد الدروس في المستوى × ساعات تقديرية لكل درس (مثلاً 1 ساعة)
                $lessonsCount = $level->lessons()->count();
                return max(1, $lessonsCount); // على الأقل ساعة واحدة
            }
        }

        // إذا كان للكورس، حساب ساعات جميع المستويات
        $course = $certificate->course;
        if ($course) {
            $totalLessons = 0;
            foreach ($course->levels as $level) {
                $totalLessons += $level->lessons()->count();
            }
            return max(1, $totalLessons); // على الأقل ساعة واحدة
        }

        return 1; // قيمة افتراضية
    }

    /**
     * تحويل الرقم إلى نص عربي
     */
    private function numberToArabicWords(int $number): string
    {
        $ones = [
            0 => '',
            1 => 'واحدة',
            2 => 'اثنتين',
            3 => 'ثلاث',
            4 => 'أربع',
            5 => 'خمس',
            6 => 'ست',
            7 => 'سبع',
            8 => 'ثماني',
            9 => 'تسع',
            10 => 'عشر',
            11 => 'إحدى عشرة',
            12 => 'اثنتا عشرة',
            13 => 'ثلاث عشرة',
            14 => 'أربع عشرة',
            15 => 'خمس عشرة',
            16 => 'ست عشرة',
            17 => 'سبع عشرة',
            18 => 'ثماني عشرة',
            19 => 'تسع عشرة',
            20 => 'عشرين',
        ];

        if ($number <= 20) {
            return $ones[$number] ?? (string)$number;
        }

        // للأرقام أكبر من 20
        $tens = (int)($number / 10);
        $remainder = $number % 10;

        $result = '';
        if ($tens > 0) {
            $tensWords = [
                2 => 'عشرين',
                3 => 'ثلاثين',
                4 => 'أربعين',
                5 => 'خمسين',
                6 => 'ستين',
                7 => 'سبعين',
                8 => 'ثمانين',
                9 => 'تسعين',
            ];
            $result = $tensWords[$tens] ?? '';
        }

        if ($remainder > 0) {
            $result = ($ones[$remainder] ?? $remainder) . ' و' . $result;
        }

        return trim($result) ?: (string)$number;
    }

    /**
     * تنسيق التاريخ بالعربية
     */
    private function formatDateInArabic($date): string
    {
        if (!$date) {
            return '';
        }

        $carbonDate = \Carbon\Carbon::parse($date);

        $days = [
            'Sunday' => 'الأحد',
            'Monday' => 'الاثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
            'Saturday' => 'السبت',
        ];

        $months = [
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'أبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر',
        ];

        $dayName = $days[$carbonDate->format('l')] ?? '';
        $day = $carbonDate->format('d');
        $month = $months[$carbonDate->format('n')] ?? '';
        $year = $carbonDate->format('Y');

        return "{$dayName}، {$day} {$month} {$year}";
    }

    /**
     * إلغاء شهادة (للمسؤولين فقط)
     */
    public function revokeCertificate(int $id, ?string $reason = null): Certificate
    {
        $certificate = $this->show($id);

        // TODO: إضافة حقل status و revoked_at إذا لزم الأمر
        // حالياً يمكن فقط حذف الشهادة بشكل soft delete
        // $certificate->status = 'revoked';
        // $certificate->revoked_at = now();
        // $certificate->revoked_reason = $reason;
        // $certificate->save();

        // حذف الشهادة (soft delete)
        $certificate->delete();

        return $certificate;
    }

    /**
     * التحقق من صورة الشهادة وتوليدها إذا كانت مفقودة
     * 
     * @param int $certificateId
     * @return Certificate
     */
    public function ensureCertificateImage(Certificate $certificate): Certificate
    {
        // 1. البحث عن الشهادة
        $certificate->load(['user', 'course', 'level']);

        // 2. التحقق من وجود الصورة وتوليدها إذا لزم
        $shouldGenerateImage = false;

        if (!$certificate->image_url) {
            $shouldGenerateImage = true;
        } else {
            $imagePath = storage_path('app/public/' . $certificate->image_url);
            if (!file_exists($imagePath)) {
                $shouldGenerateImage = true;
            }
        }

        // 3. توليد الصورة إذا لزم
        if ($shouldGenerateImage) {
            try {
                $imagePath = $this->generateCertificateImageFromPdf($certificate);
                if ($imagePath) {
                    $certificate->image_url = $imagePath;
                    $certificate->save();
                    $certificate->refresh();

                    Log::info("Certificate image generated successfully", [
                        'certificate_id' => $certificate->id,
                        'certificate_code' => $certificate->certificate_code,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning("Failed to generate certificate image", [
                    'certificate_id' => $certificate->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $certificate;
    }
}
