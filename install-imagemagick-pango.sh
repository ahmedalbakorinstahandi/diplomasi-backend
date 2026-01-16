#!/bin/bash

# Script لتثبيت ImageMagick مع دعم Pango

echo "=========================================="
echo "تثبيت ImageMagick مع دعم Pango"
echo "=========================================="
echo ""

# التحقق من صلاحيات root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ يجب تشغيل هذا السكريبت كـ root"
    echo "استخدم: sudo bash install-imagemagick-pango.sh"
    exit 1
fi

echo "📦 تثبيت ImageMagick..."
apt-get update
apt-get install -y imagemagick libmagickwand-dev

echo ""
echo "🔍 التحقق من دعم Pango في ImageMagick..."
IMAGEMAGICK_VERSION=$(identify -version 2>/dev/null | head -1)
echo "ImageMagick: $IMAGEMAGICK_VERSION"

PANGO_SUPPORT=$(identify -version 2>/dev/null | grep -i pangocairo)

if [ -z "$PANGO_SUPPORT" ]; then
    echo "⚠️  Pango غير موجود في ImageMagick الحالي"
    echo "📦 محاولة تثبيت ImageMagick مع دعم Pango..."
    
    # إعادة تثبيت ImageMagick مع dependencies الكاملة
    apt-get install -y --reinstall imagemagick
    
    # التحقق مرة أخرى
    PANGO_SUPPORT=$(identify -version 2>/dev/null | grep -i pangocairo)
    
    if [ -z "$PANGO_SUPPORT" ]; then
        echo "⚠️  ImageMagick الحالي لا يدعم Pango"
        echo "💡 الحل البديل: استخدام Imagick مباشرة في PHP (سيتم تطبيقه تلقائياً)"
    else
        echo "✅ Pango موجود في ImageMagick!"
        echo "$PANGO_SUPPORT"
    fi
else
    echo "✅ Pango موجود في ImageMagick!"
    echo "$PANGO_SUPPORT"
fi

echo ""
echo "🔄 إعادة تشغيل PHP-FPM..."
systemctl restart php8.2-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || echo "⚠️  تأكد من إعادة تشغيل PHP يدوياً"

echo ""
echo "=========================================="
echo "✅ تم التثبيت!"
echo "=========================================="
echo ""
echo "التحقق من النجاح:"
echo "  identify -version | grep -i pango"
echo ""
echo "إذا ظهر 'pangocairo' في الناتج، فالتثبيت نجح!"
echo ""
echo "ملاحظة: حتى بدون Pango في ImageMagick، الكود سيستخدم Imagick"
echo "لكن للدعم الكامل للعربية، يجب أن يكون Pango متاحاً"
