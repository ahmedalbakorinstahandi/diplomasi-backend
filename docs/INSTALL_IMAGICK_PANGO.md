# تثبيت Imagick مع Pango لدعم العربية بشكل ممتاز

## المشكلة الحالية

النص العربي يظهر بشكل "سباغيتي" لأن GD Driver لا يدعم العربية بشكل جيد.

## الحل الجذري

تثبيت **Imagick** مع **Pango** على السيرفر.

---

## خطوات التثبيت

### 1. الاتصال بالسيرفر

```bash
ssh root@45.132.241.51
```

### 2. التحقق من الإصدارات الحالية

```bash
# التحقق من PHP
php -v

# التحقق من Imagick (إن وجد)
php -m | grep imagick

# التحقق من ImageMagick
which convert
convert -version | grep -i pango
```

### 3. تثبيت Imagick Extension

```bash
# تحديث قائمة الحزم
apt-get update

# تثبيت Imagick extension لـ PHP
apt-get install php-imagick -y

# التحقق من التثبيت
php -m | grep imagick
```

### 4. تثبيت Pango و Cairo (للدعم الكامل للعربية)

```bash
# تثبيت المكتبات المطلوبة
apt-get install libpango1.0-dev libcairo2-dev libharfbuzz-dev -y

# إعادة بناء ImageMagick مع دعم Pango (إذا لزم)
apt-get install imagemagick-dev -y

# التحقق من دعم Pango في ImageMagick
convert -version | grep -i pango
```

إذا ظهر `pangocairo` في الناتج، فالصورة تدعم العربية بشكل ممتاز!

### 5. إعادة تشغيل PHP

```bash
# إعادة تشغيل PHP-FPM
systemctl restart php8.2-fpm

# أو إذا كان Apache
systemctl restart apache2
```

---

## التحقق من نجاح التثبيت

```bash
# 1. التحقق من Imagick extension
php -m | grep imagick

# 2. التحقق من دعم Pango
convert -version | grep pangocairo

# 3. اختبار PHP script
php -r "
if (extension_loaded('imagick')) {
    echo 'Imagick: ✅ متاح\n';
    \$img = new Imagick();
    echo 'Imagick class: ✅ يعمل\n';
} else {
    echo 'Imagick: ❌ غير متاح\n';
}
"
```

---

## بعد التثبيت

1. ✅ الكود سيتحقق تلقائياً من Imagick ويستخدمه
2. ✅ النص العربي سيظهر بشكل أفضل بكثير
3. ✅ الحروف ستكون موصولة بشكل صحيح
4. ✅ الاتجاه RTL سيعمل بشكل صحيح

---

## إذا لم يعمل

### المشكلة: Imagick غير متاح

**الحل:**
```bash
# تثبيت ImageMagick أولاً
apt-get install imagemagick libmagickwand-dev -y

# تثبيت PHP extension
apt-get install php-imagick -y

# إعادة تشغيل PHP
systemctl restart php8.2-fpm
```

### المشكلة: Pango غير متاح

**الحل:**
```bash
# تثبيت Pango
apt-get install libpango1.0-dev libcairo2-dev -y

# إعادة بناء ImageMagick
# (قد يتطلب إعادة تثبيت imagemagick)
```

---

## ملاحظة مهمة

- **GD Driver**: ❌ لا يدعم العربية بشكل جيد
- **Imagick بدون Pango**: ✅ أفضل من GD لكن ليس مثالي
- **Imagick مع Pango**: ✅✅ الحل الأمثل - دعم كامل للعربية

---

## بعد التثبيت، جرّب

1. افتح صفحة التحقق من الشهادة
2. تحقق من ظهور النص العربي بشكل صحيح
3. تحقق من الـ logs للتأكد من استخدام Imagick
