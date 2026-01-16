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
     * كتابة نص عربي على صورة باستخدام Intervention Image
     * إذا كان Imagick driver مستخدماً، سيدعم العربية بشكل أفضل تلقائياً
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
        // استخدام طريقة Intervention Image العادية
        // إذا كان Imagick driver مستخدماً، سيدعم العربية بشكل أفضل
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
