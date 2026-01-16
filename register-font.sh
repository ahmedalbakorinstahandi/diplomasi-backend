#!/bin/bash

# Script لتسجيل الخط العربي في fontconfig

echo "=========================================="
echo "تسجيل الخط العربي في fontconfig"
echo "=========================================="
echo ""

FONT_PATH="/home/ahmed-albakor-diplomasi-backend/htdocs/diplomasi-backend.ahmed-albakor.com/storage/app/fonts/itfHuwiyaDisplay-Regular.otf"

if [ ! -f "$FONT_PATH" ]; then
    echo "❌ الخط غير موجود في: $FONT_PATH"
    exit 1
fi

echo "📁 الخط موجود: $FONT_PATH"
echo ""

# إنشاء مجلد الخطوط للمستخدم إذا لم يكن موجوداً
mkdir -p ~/.local/share/fonts
mkdir -p ~/.fonts

# نسخ الخط إلى مجلد الخطوط
echo "📦 نسخ الخط إلى مجلد الخطوط..."
cp "$FONT_PATH" ~/.local/share/fonts/
cp "$FONT_PATH" ~/.fonts/

# تحديث fontconfig cache
echo "🔄 تحديث fontconfig cache..."
fc-cache -fv ~/.local/share/fonts/
fc-cache -fv ~/.fonts/

# التحقق من تسجيل الخط
echo ""
echo "🔍 التحقق من تسجيل الخط..."
fc-list | grep -i "itfHuwiya\|huwiya" || echo "⚠️  الخط غير مسجل بعد، جرب تسجيله كـ root"

echo ""
echo "=========================================="
echo "✅ تم التسجيل!"
echo "=========================================="
echo ""
echo "التحقق من الخط:"
echo "  fc-list | grep -i huwiya"
echo ""
echo "ملاحظة: إذا لم يعمل، جرب تسجيل الخط بشكل عام:"
echo "  sudo cp $FONT_PATH /usr/share/fonts/truetype/"
echo "  sudo fc-cache -fv"
