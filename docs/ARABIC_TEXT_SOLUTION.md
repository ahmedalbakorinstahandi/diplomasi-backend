# حل مشكلة عرض النص العربي في الشهادات

## المشكلة

النص العربي يظهر بشكل "سباغيتي" (حروف غير موصولة ومقلوبة) بسبب:
- **GD Driver** في PHP لا يدعم RTL (Right-to-Left) بشكل جيد
- **GD Driver** لا يدعم Ligatures (اتصال الحروف) في الخطوط العربية
- عدم وجود دعم للـ Arabic Shaping في GD

---

## الحل المطبق حالياً

### 1. استخدام Imagick بدلاً من GD (إذا كان متاحاً)
- ✅ Imagick يدعم العربية بشكل أفضل من GD
- ✅ يتم التحقق تلقائياً من توفر Imagick
- ✅ إذا لم يكن متاحاً، يتم استخدام GD مع معالجة أساسية

### 2. معالجة النص العربي
- ✅ إضافة LRM (Left-to-Right Mark) للكلمات الإنجليزية
- ✅ استخدام خط عربي (`itfHuwiyaDisplay-Regular.otf`)

---

## الحل الجذري المطلوب

للحصول على عرض احترافي للنص العربي مع اتصال الحروف، يجب:

### الخيار 1: تثبيت Imagick مع Pango على السيرفر (الأفضل)

```bash
# على السيرفر (Ubuntu/Debian)
sudo apt-get update
sudo apt-get install php-imagick libmagickwand-dev imagemagick
sudo apt-get install libpango1.0-dev libharfbuzz-dev libcairo2-dev

# إعادة تشغيل PHP-FPM أو Apache
sudo systemctl restart php8.2-fpm
# أو
sudo systemctl restart apache2
```

**التحقق:**
```bash
php -m | grep imagick
php -r "echo extension_loaded('imagick') ? 'Imagick loaded' : 'Imagick not loaded';"
```

### الخيار 2: استخدام مكتبة متخصصة

يمكن استخدام مكتبة مثل `ar-php` لمعالجة النص العربي:

```bash
composer require khaled.alshamaa/ar-php
```

ثم تحديث `ArabicTextRenderer` لاستخدامها.

### الخيار 3: استخدام Node.js Canvas API

إنشاء خدمة صغيرة في Node.js تستخدم Canvas API (يدعم العربية بشكل ممتاز):

```javascript
const { createCanvas, loadImage, registerFont } = require('canvas');
registerFont('path/to/font.otf', { family: 'ArabicFont' });

// Render Arabic text properly
```

ثم استدعاء هذه الخدمة من PHP.

---

## التحقق من الحل الحالي

بعد رفع الكود المحدث إلى السيرفر:

1. **التحقق من Imagick:**
   ```bash
   ssh root@45.132.241.51 "php -m | grep imagick"
   ```

2. **اختبار توليد شهادة:**
   - افتح صفحة التحقق من الشهادة
   - تحقق من الـ logs

3. **إذا كان Imagick غير مثبت:**
   - النص العربي سيظهر بشكل أفضل من قبل لكن قد لا يكون مثالي
   - للحل الجذري: ثبّت Imagick مع Pango (الخيار 1)

---

## الخطوات التالية

1. ✅ **تم**: تحديث الكود لاستخدام Imagick إذا كان متاحاً
2. ✅ **تم**: إضافة معالجة أساسية للنص العربي
3. ⚠️ **مطلوب**: تثبيت Imagick مع Pango على السيرفر للحل الجذري
4. ⚠️ **اختياري**: استخدام مكتبة متخصصة في معالجة النص العربي

---

## ملاحظات مهمة

- **GD Driver**: لا يدعم العربية بشكل جيد - الحروف ستظهر غير موصولة
- **Imagick بدون Pango**: أفضل من GD لكن قد لا يكون مثالي
- **Imagick مع Pango**: الحل الأمثل - يدعم اتصال الحروف وRTL بشكل صحيح

---

## الأوامر لتثبيت Imagick مع Pango

```bash
# على السيرفر
ssh root@45.132.241.51

# تثبيت Imagick extension
apt-get update
apt-get install php-imagick -y

# تثبيت Pango و Cairo (للعربية)
apt-get install libpango1.0-dev libcairo2-dev libharfbuzz-dev -y

# إعادة بناء ImageMagick مع دعم Pango
apt-get install imagemagick-dev -y
pecl install imagick

# إعادة تشغيل PHP
systemctl restart php8.2-fpm
```

---

## التحقق من نجاح التثبيت

```bash
# التحقق من Imagick
php -m | grep imagick

# التحقق من دعم Pango في ImageMagick
identify -version | grep -i pango
```

إذا ظهر `pangocairo` في الناتج، فالصورة تدعم العربية بشكل ممتاز!
