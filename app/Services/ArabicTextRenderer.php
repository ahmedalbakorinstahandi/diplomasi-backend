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
     * كتابة نص عربي على صورة باستخدام Pango markup في ImageMagick
     * هذا يدعم العربية بشكل ممتاز مع اتصال الحروف
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
        // محاولة استخدام Pango markup إذا كان ImageMagick يدعمه
        if (extension_loaded('imagick') && class_exists('\Imagick')) {
            try {
                // الحصول على Imagick object من Intervention Image
                $core = $image->core();
                if (method_exists($core, 'native')) {
                    $imagick = $core->native();
                    
                    if ($imagick instanceof \Imagick) {
                        // التحقق من دعم Pango في ImageMagick
                        $versionInfo = $imagick->getVersion();
                        $versionString = $versionInfo['versionString'] ?? '';
                        
                        if (stripos($versionString, 'pangocairo') !== false || 
                            stripos($versionString, 'pango') !== false) {
                            
                            // استخدام Pango markup للعربية
                            $width = $imagick->getImageWidth();
                            $height = $imagick->getImageHeight();
                            
                            // تحضير النص للـ Pango markup
                            $escapedText = htmlspecialchars($text, ENT_XML1, 'UTF-8');
                            
                            // بناء Pango markup مع الخط واللون والحجم
                            // Pango يحتاج اسم الخط أو file:// URI
                            $fontSpec = 'Arial'; // Default
                            
                            if ($fontPath && file_exists($fontPath)) {
                                // محاولة الحصول على اسم الخط من fontconfig أولاً
                                $fontName = self::getFontNameFromPath($fontPath);
                                
                                if ($fontName) {
                                    // استخدام اسم الخط المسجل في fontconfig
                                    $fontSpec = $fontName;
                                } else {
                                    // استخدام file:// URI كبديل
                                    $fontSpec = 'file://' . $fontPath;
                                }
                            }
                            
                            // Pango markup مع RTL وArabic shaping
                            $pangoMarkup = sprintf(
                                '<span font="%s" size="%d" foreground="%s" dir="rtl" lang="ar">%s</span>',
                                htmlspecialchars($fontSpec, ENT_XML1, 'UTF-8'),
                                (int)($fontSize * 1024), // Pango uses 1024th of a point (1 point = 1024 units)
                                $color,
                                $escapedText
                            );
                            
                            // إنشاء صورة نصية باستخدام Pango
                            // نحتاج إلى حساب عرض النص تقريبياً
                            $textWidth = $width * 0.9; // 90% من عرض الصورة
                            $textHeight = $fontSize * 4; // ارتفاع كافٍ للنص (أكبر قليلاً)
                            
                            try {
                                $textImage = new \Imagick();
                                $textImage->setBackgroundColor(new \ImagickPixel('transparent'));
                                
                                // استخدام Pango markup لإنشاء صورة النص
                                $pangoCommand = "pango:{$pangoMarkup}";
                                $textImage->newPseudoImage(
                                    (int)$textWidth,
                                    (int)$textHeight,
                                    $pangoCommand
                                );
                                
                                // دمج صورة النص مع الصورة الأساسية
                                // حساب الموضع للوسط
                                $textImgWidth = $textImage->getImageWidth();
                                $textImgHeight = $textImage->getImageHeight();
                                
                                $destX = (int)($x - ($textImgWidth / 2));
                                $destY = (int)$y;
                                
                                // التأكد من أن المواضع صحيحة
                                if ($destX < 0) $destX = 0;
                                if ($destY < 0) $destY = 0;
                                
                                $imagick->compositeImage(
                                    $textImage,
                                    \Imagick::COMPOSITE_OVER,
                                    $destX,
                                    $destY
                                );
                                
                                $textImage->destroy();
                                
                                Log::info("Used Pango markup for Arabic text", [
                                    'text' => substr($text, 0, 50),
                                    'font' => $fontSpec,
                                    'pango_support' => 'yes',
                                    'text_size' => "{$textImgWidth}x{$textImgHeight}",
                                    'position' => "{$destX},{$destY}",
                                ]);
                                
                                return $image;
                            } catch (\Throwable $e) {
                                Log::warning("Pango markup failed, trying standard Imagick", [
                                    'error' => $e->getMessage(),
                                    'pango_markup' => substr($pangoMarkup, 0, 100),
                                ]);
                                // Continue to fallback
                            }
                        }
                        
                        // Fallback: استخدام ImagickDraw العادي
                        $draw = new \ImagickDraw();
                        
                        if ($fontPath && file_exists($fontPath)) {
                            $draw->setFont($fontPath);
                        }
                        
                        $draw->setFontSize($fontSize);
                        $draw->setFillColor(new \ImagickPixel($color));
                        $draw->setTextAlignment(\Imagick::ALIGN_CENTER);
                        $draw->setGravity(\Imagick::GRAVITY_NORTH);
                        
                        // كتابة النص (بدون عكس - قد يعمل بشكل أفضل الآن مع Imagick)
                        $imagick->annotateImage($draw, $x, $y, 0, $text);
                        
                        Log::info("Used Imagick Draw for Arabic text", [
                            'text' => substr($text, 0, 50),
                            'font' => $fontPath ? basename($fontPath) : 'default',
                        ]);
                        
                        return $image;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to use Imagick, falling back to Intervention Image", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
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
     * الحصول على اسم الخط من fontconfig باستخدام مسار الخط
     */
    private static function getFontNameFromPath(string $fontPath): ?string
    {
        // محاولة الحصول على اسم الخط من fontconfig
        // باستخدام fc-query (إذا كان متاحاً)
        if (function_exists('shell_exec')) {
            $command = "fc-query --format='%{family}' " . escapeshellarg($fontPath) . " 2>/dev/null";
            $fontName = @shell_exec($command);
            
            if ($fontName && trim($fontName)) {
                return trim($fontName);
            }
        }
        
        // إذا فشل، إرجاع null لاستخدام file:// URI
        return null;
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
