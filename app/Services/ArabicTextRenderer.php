<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * خدمة متخصصة في عرض النص العربي بشكل صحيح
 * تعالج مشاكل RTL واتصال الحروف
 */
class ArabicTextRenderer
{
    /**
     * كتابة نص عربي على صورة باستخدام Imagick مباشرة
     * محاولة استخدام Imagick مباشرة للحصول على أفضل دعم للعربية
     */
    public static function writeArabicText(
        ImageInterface $image,
        string $text,
        float $x,
        float $y,
        int $fontSize,
        string $color,
        string $fontPath,
        ImageManager $manager
    ): ImageInterface {
        // محاولة استخدام Imagick مباشرة إذا كان متاحاً
        if (extension_loaded('imagick') && class_exists('\Imagick') && class_exists('\ImagickDraw')) {
            try {
                // الحصول على Imagick object من Intervention Image
                $core = $image->core();
                if (method_exists($core, 'native')) {
                    $imagick = $core->native();
                    
                    if ($imagick instanceof \Imagick) {
                        $draw = new \ImagickDraw();
                        
                        // إعداد الخط
                        if ($fontPath && file_exists($fontPath)) {
                            $draw->setFont($fontPath);
                        }
                        
                        $draw->setFontSize($fontSize);
                        $draw->setFillColor(new \ImagickPixel($color));
                        $draw->setTextAlignment(\Imagick::ALIGN_CENTER);
                        $draw->setGravity(\Imagick::GRAVITY_NORTH);
                        
                        // محاولة تحسين العربية: عكس النص يدوياً
                        // لأن Imagick بدون Pango يكتبه بشكل معكوس
                        $processedText = self::reverseArabicText($text);
                        
                        // كتابة النص
                        $imagick->annotateImage($draw, $x, $y, 0, $processedText);
                        
                        Log::info("Used Imagick directly for Arabic text", [
                            'text' => substr($text, 0, 50),
                            'font' => $fontPath ? basename($fontPath) : 'default',
                        ]);
                        
                        return $image;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to use Imagick directly, falling back", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Fallback: استخدام طريقة Intervention Image العادية
        $image->text($text, $x, $y, function ($font) use ($fontPath, $fontSize, $color) {
            if ($fontPath && file_exists($fontPath)) {
                $font->filename($fontPath);
            }
            $font->size($fontSize);
            $font->color($color);
            $font->align('center');
            $font->valign('top');
        });
        
        return $image;
    }
    
    /**
     * عكس ترتيب النص العربي لأن Imagick بدون Pango يكتبه بشكل معكوس
     */
    private static function reverseArabicText(string $text): string
    {
        // إذا لم يكن هناك نص عربي، إرجاعه كما هو
        if (!preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return $text;
        }
        
        // فصل النص إلى كلمات
        $words = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $reversed = [];
        
        foreach ($words as $word) {
            // إذا كانت الكلمة عربية، عكس ترتيب الحروف
            if (preg_match('/[\x{0600}-\x{06FF}]/u', $word)) {
                // عكس ترتيب الحروف
                $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
                $reversed[] = implode('', array_reverse($chars));
            } else {
                // الكلمات الإنجليزية تبقى كما هي
                $reversed[] = $word;
            }
        }
        
        // عكس ترتيب الكلمات أيضاً (RTL)
        return implode('', array_reverse($reversed));
    }
    
    /**
     * معالجة النص العربي لضمان الاتجاه الصحيح
     * 
     * ملاحظة مهمة: بدون Pango، GD/Imagick لا يدعم Arabic shaping بشكل صحيح.
     * الحل الجذري: تثبيت Pango على السيرفر (راجع docs/ARABIC_TEXT_FIX.md)
     */
    public static function prepareArabicText(string $text): string
    {
        // التحقق من وجود نص عربي
        if (!preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return $text;
        }
        
        // إضافة LRM للكلمات الإنجليزية في النص المختلط
        $text = preg_replace('/([a-zA-Z0-9]+)/u', "\xE2\x80\x8E$1\xE2\x80\x8E", $text);
        
        // بدون Pango، لا يمكن ضمان اتصال الحروف بشكل صحيح
        // لكن نحاول على الأقل تحسين الاتجاه
        return $text;
    }
}
