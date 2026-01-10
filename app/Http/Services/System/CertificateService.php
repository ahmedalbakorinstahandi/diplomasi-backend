<?php

namespace App\Http\Services\System;

use App\Http\Permissions\System\CertificatePermission;
use App\Models\Learning\Course;
use App\Models\Learning\Level;
use App\Models\Progress\UserCourse;
use App\Models\Progress\UserLevelProgress;
use App\Models\System\Certificate;
use App\Services\FilterService;
use App\Services\ImageService;
use App\Services\MessageService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateService
{
    // مسار صورة القالب
    private const TEMPLATE_PATH = 'certificates/templates/certificate-template.png';

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

        // توليد صورة الشهادة
        $imagePath = $this->generateCertificateImage($certificate);
        $certificate->image_url = $imagePath;
        $certificate->save();

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
     * توليد صورة الشهادة من القالب مع Text Overlay
     */
    public function generateCertificateImage(Certificate $certificate): string
    {
        $manager = new ImageManager(new Driver());

        // تحميل صورة القالب
        $templatePath = storage_path('app/public/' . self::TEMPLATE_PATH);
        
        if (!file_exists($templatePath)) {
            MessageService::abort(500, 'صورة القالب غير موجودة. يرجى حفظ القالب في: ' . self::TEMPLATE_PATH);
        }

        $image = $manager->read($templatePath);

        // تحميل العلاقات المطلوبة
        $certificate->load(['user', 'course', 'level']);

        // TODO: تحديد المواضع الدقيقة للنصوص حسب القالب الفعلي
        // المواضع الحالية تقريبية وتحتاج تعديل حسب القالب

        // 1. كتابة اسم المستلم
        $userName = trim($certificate->user->first_name . ' ' . $certificate->user->last_name);
        if (!empty($userName)) {
            // الموضع التقريبي - X: 1200 (منتصف الصورة تقريباً), Y: 600
            // TODO: استخدام خط عربي مناسب عند توفر الخطوط
            $fontPath = storage_path('fonts/arial.ttf');
            if (!file_exists($fontPath)) {
                // إذا لم يكن الخط موجوداً، سنستخدم الخط الافتراضي
                $fontPath = null;
            }
            
            $image->text($userName, 1200, 600, function ($font) use ($fontPath) {
                if ($fontPath) {
                    $font->filename($fontPath);
                }
                $font->size(48);
                $font->color('#1a1a5e');
                $font->align('center');
                $font->valign('middle');
            });
        }

        // 2. كتابة اسم الكورس
        $courseTitle = $certificate->course->title;
        if (!empty($courseTitle)) {
            $fontPath = storage_path('fonts/arial-bold.ttf');
            if (!file_exists($fontPath)) {
                $fontPath = storage_path('fonts/arial.ttf');
                if (!file_exists($fontPath)) {
                    $fontPath = null;
                }
            }
            
            $image->text($courseTitle, 1200, 800, function ($font) use ($fontPath) {
                if ($fontPath) {
                    $font->filename($fontPath);
                }
                $font->size(36);
                $font->color('#D4A017');
                $font->align('center');
                $font->valign('middle');
            });
        }

        // 3. كتابة مدة التدريب
        $hours = $this->calculateTrainingHours($certificate);
        $hoursText = $this->numberToArabicWords($hours);
        $trainingDuration = "بمدة تدريبية قدرها {$hoursText} ({$hours}) ساعة تدريبية";
        
        $fontPath = storage_path('fonts/arial.ttf');
        if (!file_exists($fontPath)) {
            $fontPath = null;
        }
        
        $image->text($trainingDuration, 1200, 1000, function ($font) use ($fontPath) {
            if ($fontPath) {
                $font->filename($fontPath);
            }
            $font->size(24);
            $font->color('#1a1a5e');
            $font->align('center');
            $font->valign('middle');
        });

        // 4. كتابة التاريخ
        $date = $this->formatDateInArabic($certificate->issued_at);
        $fontPath = storage_path('fonts/arial.ttf');
        if (!file_exists($fontPath)) {
            $fontPath = null;
        }
        
        $image->text("التاريخ: {$date}", 300, 1600, function ($font) use ($fontPath) {
            if ($fontPath) {
                $font->filename($fontPath);
            }
            $font->size(20);
            $font->color('#1a1a5e');
            $font->align('left');
            $font->valign('top');
        });

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

        return "{$outputFolder}/{$certificate->certificate_code}.png";
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
