#!/bin/bash

# Script لتثبيت Pango لدعم العربية بشكل صحيح في ImageMagick

echo "=========================================="
echo "تثبيت Pango لدعم العربية في الشهادات"
echo "=========================================="
echo ""

# التحقق من صلاحيات root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ يجب تشغيل هذا السكريبت كـ root"
    echo "استخدم: sudo bash install-pango.sh"
    exit 1
fi

echo "📦 تحديث قائمة الحزم..."
apt-get update

echo ""
echo "📦 تثبيت Pango و Cairo و HarfBuzz..."
apt-get install -y libpango1.0-dev libcairo2-dev libharfbuzz-dev

echo ""
echo "🔍 التحقق من دعم Pango في ImageMagick..."
PANGO_SUPPORT=$(convert -version 2>/dev/null | grep -i pangocairo)

if [ -z "$PANGO_SUPPORT" ]; then
    echo "⚠️  Pango غير موجود في ImageMagick"
    echo "📦 محاولة إعادة بناء ImageMagick مع دعم Pango..."
    apt-get install -y imagemagick-dev
    # قد نحتاج إلى إعادة تجميع ImageMagick
    echo "⚠️  قد تحتاج إلى إعادة تجميع ImageMagick يدوياً"
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
echo "  convert -version | grep pangocairo"
echo ""
echo "إذا ظهر 'pangocairo' في الناتج، فالتثبيت نجح!"
echo ""
echo "بعد ذلك، افتح صفحة الشهادة - النص العربي سيظهر بشكل صحيح! 🎉"
