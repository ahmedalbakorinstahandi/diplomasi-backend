<?php

namespace App\Http\Services\System;

use App\Http\Permissions\System\CertificatePermission;
use App\Models\Learning\Course;
use App\Models\Learning\Level;
use App\Models\Progress\UserCourse;
use App\Models\Progress\UserLevelProgress;
use App\Models\System\Certificate;
use App\Services\ArabicTextRenderer;
use App\Services\FilterService;
use App\Services\ImageService;
use App\Services\MessageService;
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
    // مسار صورة القالب
    // يمكن أن يكون في templates/ أو qr/templates/
    private const TEMPLATE_PATH = 'certificates/templates/certificate-template.png';
    private const TEMPLATE_PATH_ALTERNATIVE = 'certificates/qr/templates/certificate-template.png';

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
    public function checkCertificateEligibility(int $userId, int $courseId, ?int $levelId = null): array
    {
        // سيناريو 1: إكمال الكورس (level_id = null) - يحتاج UserCourse
        if ($levelId === null) {
            $userCourse = UserCourse::where('user_id', $userId)
                ->where('course_id', $courseId)
                ->first();

            if (!$userCourse) {
                return [
                    'eligible' => false,
                    'reason' => 'المستخدم غير مسجل في هذا الكورس',
                    'type' => 'course',
                ];
            }
            if ($userCourse->status !== 'completed') {
                return [
                    'eligible' => false,
                    'reason' => 'الكورس لم يكتمل بعد',
                    'type' => 'course',
                ];
            }

            if (!$userCourse->completed_at) {
                return [
                    'eligible' => false,
                    'reason' => 'تاريخ الإكمال غير موجود',
                    'type' => 'course',
                ];
            }

            // التحقق من أن جميع المستويات مكتملة
            $course = Course::find($courseId);
            if (!$course) {
                return [
                    'eligible' => false,
                    'reason' => 'الكورس غير موجود',
                    'type' => 'course',
                ];
            }

            $levels = $course->levels()->get();
            foreach ($levels as $level) {
                $userLevelProgress = UserLevelProgress::where('user_id', $userId)
                    ->where('level_id', $level->id)
                    ->first();

                if (!$userLevelProgress || $userLevelProgress->status !== 'completed') {
                    return [
                        'eligible' => false,
                        'reason' => 'بعض المستويات غير مكتملة',
                        'type' => 'course',
                    ];
                }
            }

            // التحقق من عدم وجود شهادة سابقة للكورس
            $existingCertificate = Certificate::where('user_id', $userId)
                ->where('course_id', $courseId)
                ->whereNull('level_id')
                ->first();

            if ($existingCertificate) {
                return [
                    'eligible' => false,
                    'reason' => 'تم إصدار شهادة سابقة لهذا الكورس',
                    'type' => 'course',
                ];
            }

            return [
                'eligible' => true,
                'reason' => 'المستخدم مؤهل للحصول على شهادة الكورس',
                'type' => 'course',
            ];
        }

        // سيناريو 2: إكمال مستوى محدد (level_id محدد)
        $level = Level::find($levelId);
        if (!$level) {
            return [
                'eligible' => false,
                'reason' => 'المستوى غير موجود',
                'type' => 'level',
            ];
        }

        if ($level->course_id != $courseId) {
            return [
                'eligible' => false,
                'reason' => 'المستوى لا ينتمي لهذا الكورس',
                'type' => 'level',
            ];
        }

        if (!$level->has_certificate) {
            return [
                'eligible' => false,
                'reason' => 'هذا المستوى لا يحتوي على شهادة',
                'type' => 'level',
            ];
        }

        $userLevelProgress = UserLevelProgress::where('user_id', $userId)
            ->where('level_id', $levelId)
            ->first();

        if (!$userLevelProgress) {
            return [
                'eligible' => false,
                'reason' => 'المستخدم غير مسجل في هذا المستوى',
                'type' => 'level',
            ];
        }

        if ($userLevelProgress->status !== 'completed') {
            return [
                'eligible' => false,
                'reason' => 'المستوى لم يكتمل بعد',
                'type' => 'level',
            ];
        }

        if (!$userLevelProgress->completed_at) {
            return [
                'eligible' => false,
                'reason' => 'تاريخ إكمال المستوى غير موجود',
                'type' => 'level',
            ];
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
            return [
                'eligible' => false,
                'reason' => 'تم إصدار شهادة سابقة لهذا المستوى',
                'type' => 'level',
            ];
        }

        return [
            'eligible' => true,
            'reason' => 'المستخدم مؤهل للحصول على شهادة المستوى',
            'type' => 'level',
        ];
    }

    /**
     * إصدار شهادة جديدة
     */
    public function issueCertificate(int $userId, int $courseId, ?int $levelId = null)
    {
        // التحقق من الأهلية
        $eligibility = $this->checkCertificateEligibility($userId, $courseId, $levelId);
        if (!$eligibility['eligible']) {
            MessageService::abort(400, $eligibility['reason']);
        }

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
        $this->sendCertificateIssuedNotification($certificate);

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
     * توليد PDF الشهادة مباشرة للعرض في المتصفح (الطريقة الجديدة - أفضل للعربية)
     */
    public function generateCertificatePdf(Certificate $certificate): \Illuminate\Http\Response
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

            Log::info("Generated HTML from Blade template for PDF", [
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
                'format' => [1200, 850], // 1200x850 pixels
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
            
            // dejavusans يدعم العربية بشكل ممتاز - لا حاجة لتسجيل خط إضافي
            Log::info("Using dejavusans font for Arabic support in mPDF", [
                'note' => 'dejavusans has excellent Arabic support built-in',
            ]);

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
            throw new \Exception('فشل في توليد PDF الشهادة: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Error generating certificate PDF", [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception('فشل في توليد PDF الشهادة: ' . $e->getMessage());
        }
    }

    /**
     * توليد PNG من PDF (للعرض كصورة - اختياري)
     * هذه المرة نستخدم PDF الذي تم إنشاؤه بـ mPDF (يدعم العربية بشكل ممتاز)
     */
    public function generateCertificateImageFromPdf(Certificate $certificate): ?string
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
                'format' => [1200, 850],
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
                'dpi' => 300, // دقة عالية للصورة
            ]);
            
            Log::info("Using dejavusans font for Arabic support (PNG conversion)", [
                'note' => 'dejavusans has excellent Arabic support built-in',
            ]);

            $mpdf->SetDirectionality('rtl');
            $mpdf->WriteHTML($html);

            // حفظ PDF مؤقتاً
            $tempPdfPath = storage_path('app/temp/certificate_' . $certificate->id . '_' . time() . '.pdf');
            $tempDir = dirname($tempPdfPath);
            if (!File::exists($tempDir)) {
                File::makeDirectory($tempDir, 0755, true);
            }
            $mpdf->Output($tempPdfPath, 'F');

            // تحويل PDF إلى PNG باستخدام Imagick
            if (!extension_loaded('imagick')) {
                throw new \Exception('Imagick extension is required to convert PDF to PNG');
            }

            $imagick = new \Imagick();
            $imagick->setResolution(300, 300); // دقة عالية
            $imagick->readImage($tempPdfPath . '[0]');
            $imagick->setImageFormat('png');
            $imagick->setImageBackgroundColor(new \ImagickPixel('white'));
            $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

            // حفظ PNG
            $imagePath = 'certificates/' . $certificate->certificate_code . '.png';
            $fullImagePath = storage_path('app/public/' . $imagePath);
            $imageDir = dirname($fullImagePath);
            if (!File::exists($imageDir)) {
                File::makeDirectory($imageDir, 0755, true);
            }

            $imagick->writeImage($fullImagePath);
            $imagick->destroy();

            // حذف PDF المؤقت
            if (File::exists($tempPdfPath)) {
                File::delete($tempPdfPath);
            }

            Log::info("Converted PDF to PNG successfully", [
                'certificate_id' => $certificate->id,
                'image_path' => $imagePath,
            ]);

            return $imagePath;

        } catch (\Exception $e) {
            Log::error("Error converting PDF to PNG", [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
            ]);
            return null; // إرجاع null بدلاً من throw - لأن PNG اختياري
        }
    }

    /**
     * توليد صورة الشهادة من Blade template (الطريقة القديمة - محفوظة للرجوع)
     */
    public function generateCertificateImageFromBlade(Certificate $certificate): string
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
                'format' => [1200, 850], // 1200x850 pixels
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

            // تحويل PDF إلى PNG باستخدام Imagick
            if (!extension_loaded('imagick')) {
                throw new \Exception('Imagick extension is required to convert PDF to PNG');
            }

            $imagick = new \Imagick();
            $imagick->setResolution(300, 300); // عالية الدقة
            $imagick->readImage($tempPdfPath . '[0]'); // قراءة الصفحة الأولى فقط
            $imagick->setImageFormat('png');
            $imagick->setImageBackgroundColor(new \ImagickPixel('white'));
            $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

            // حفظ PNG
            $imagePath = 'certificates/' . $certificate->certificate_code . '.png';
            $fullImagePath = storage_path('app/public/' . $imagePath);
            $imageDir = dirname($fullImagePath);
            if (!File::exists($imageDir)) {
                File::makeDirectory($imageDir, 0755, true);
            }

            $imagick->writeImage($fullImagePath);
            $imagick->destroy();

            // حذف PDF المؤقت
            if (File::exists($tempPdfPath)) {
                File::delete($tempPdfPath);
            }

            Log::info("Converted PDF to PNG", [
                'certificate_id' => $certificate->id,
                'image_path' => $imagePath,
            ]);

            return $imagePath;

        } catch (MpdfException $e) {
            Log::error("Error generating certificate image from Blade (mPDF)", [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('فشل في توليد صورة الشهادة: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Error generating certificate image from Blade", [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception('فشل في توليد صورة الشهادة: ' . $e->getMessage());
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
            $writeArabicText = function($text, $x, $y, $fontSize, $color) use (&$image, $arabicFontPath, $manager, $useImagick) {
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
                throw new \Exception("فشل في حفظ صورة الشهادة في: {$outputPath}");
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
            throw new \Exception('فشل في توليد صورة الشهادة: ' . $e->getMessage(), 0, $e);
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

            $qrCodePath = storage_path("app/public/{$qrFolder}/{$certificate->certificate_code}.png");

            // توليد QR Code باستخدام SVG (لأن PNG يحتاج imagick extension)
            // SVG يعمل بدون imagick extension
            $qrCodeSvgPath = storage_path("app/public/{$qrFolder}/{$certificate->certificate_code}.svg");
            
            QrCode::format('svg')
                ->size(300)
                ->generate($verificationUrl, $qrCodeSvgPath);

            // التحقق من أن SVG تم إنشاؤه
            if (!File::exists($qrCodeSvgPath)) {
                throw new \Exception("فشل في توليد QR Code SVG");
            }

            // استخدام SVG مباشرة (أو يمكن تحويله إلى PNG لاحقاً إذا كان imagick مثبت)
            // حالياً سنستخدم SVG لأنه يعمل بدون imagick
            $qrCodePath = $qrCodeSvgPath;
            
            // تحديث المسار للإرجاع ليشير إلى SVG
            $qrFolder = 'certificates/qr';
            $returnPath = "{$qrFolder}/{$certificate->certificate_code}.svg";

            // التحقق من أن الملف تم إنشاؤه بنجاح
            if (!File::exists($qrCodePath)) {
                Log::error("Failed to generate QR code", [
                    'certificate_id' => $certificate->id,
                    'certificate_code' => $certificate->certificate_code,
                    'qr_code_path' => $qrCodePath,
                ]);
                throw new \Exception("فشل في توليد QR Code للشهادة");
            }

            Log::info("QR code generated successfully", [
                'certificate_id' => $certificate->id,
                'certificate_code' => $certificate->certificate_code,
                'qr_code_path' => $qrCodePath,
                'format' => 'svg',
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
                'message' => 'الشهادة غير موجودة',
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
            MessageService::abort(404, 'صورة الشهادة غير موجودة');
        }

        $imagePath = storage_path('app/public/' . $certificate->image_url);

        if (!file_exists($imagePath)) {
            MessageService::abort(404, 'ملف الصورة غير موجود');
        }

        return response()->download($imagePath, $certificate->certificate_code . '.png');
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
    public function revokeCertificate(int $id, string $reason = null): Certificate
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
     * إرسال إشعار للمستخدم عند إصدار الشهادة
     */
    private function sendCertificateIssuedNotification(Certificate $certificate): void
    {
        // TODO: إضافة إشعار للمستخدم
        // يمكن استخدام NotificationService الموجود
        // $notificationService = app(\App\Http\Services\System\NotificationService::class);
        // $notificationService->create([
        //     'user_id' => $certificate->user_id,
        //     'title' => 'تم إصدار شهادة جديدة',
        //     'body' => 'تهانينا! تم إصدار شهادتك في ' . $certificate->course->title,
        //     'type' => 'certificate_issued',
        // ]);
    }
}
