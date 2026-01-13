# دليل إعداد وتوليد صورة الشهادة

هذا الدليل يشرح الخطوات التفصيلية لإعداد وتوليد صورة الشهادة بشكل احترافي.

---

## 📋 الخطوات المطلوبة

### الخطوة 1: إعداد صورة القالب (Template Image)

#### 1.1. تحضير صورة القالب
- **الموقع المطلوب**: `storage/app/public/certificates/templates/certificate-template.png`
- **المواصفات الموصى بها**:
  - **الصيغة**: PNG (شفاف أو بخلفية)
  - **الحجم**: 2400x1600 بكسل (أو أي حجم مناسب للطباعة)
  - **الدقة**: 300 DPI (للطباعة عالية الجودة)
  - **الخلفية**: تصميم الشهادة بدون بيانات (فقط القالب)

#### 1.2. رفع صورة القالب
```bash
# إنشاء المجلد إذا لم يكن موجوداً
mkdir -p storage/app/public/certificates/templates

# رفع صورة القالب إلى:
storage/app/public/certificates/templates/certificate-template.png
```

**أو** استخدم ملف upload API:
```http
POST /api/v1/general/upload-image
Content-Type: multipart/form-data

file: [صورة القالب]
```

---

### الخطوة 2: إعداد الخطوط العربية

#### 2.1. تحميل الخطوط
تحتاج إلى خطوط عربية تدعم TTF/OTF:
- **مثال**: Arial Unicode MS, Tahoma, Amiri, Noto Sans Arabic, etc.

#### 2.2. حفظ الخطوط
- **الموقع المطلوب**: `storage/fonts/`
- **الأسماء المطلوبة**:
  - `arial.ttf` (للنصوص العادية)
  - `arial-bold.ttf` (للنصوص العريضة)
  - أو أي خط عربي آخر (مثل `amiri.ttf`, `tahoma.ttf`)

```bash
# إنشاء مجلد الخطوط
mkdir -p storage/fonts

# نسخ الخطوط إلى المجلد
# مثال:
# cp /path/to/arial.ttf storage/fonts/
# cp /path/to/arial-bold.ttf storage/fonts/
```

---

### الخطوة 3: تحديد المواضع الدقيقة للنصوص

#### 3.1. فتح صورة القالب في برنامج تصميم
- استخدم برنامج مثل **Photoshop**, **GIMP**, أو **Figma**
- افتح `certificate-template.png`

#### 3.2. تحديد مواضع النصوص
للكل نص، حدد:
- **X Position**: الموضع الأفقي (من اليسار)
- **Y Position**: الموضع العمودي (من الأعلى)
- **Font Size**: حجم الخط
- **Color**: لون النص (HEX code)
- **Alignment**: محاذاة (center, left, right)

#### 3.3. النصوص المطلوبة في الشهادة

1. **اسم المستخدم**
   - **الموضع الحالي**: X: 1200, Y: 600 (تقريبي)
   - **الحجم الحالي**: 48px
   - **اللون الحالي**: #1a1a5e
   - **المحاذاة**: center

2. **اسم الكورس/المستوى**
   - **الموضع الحالي**: X: 1200, Y: 800 (تقريبي)
   - **الحجم الحالي**: 36px
   - **اللون الحالي**: #D4A017 (ذهبي)
   - **المحاذاة**: center

3. **مدة التدريب**
   - **الموضع الحالي**: X: 1200, Y: 1000 (تقريبي)
   - **الحجم الحالي**: 24px
   - **اللون الحالي**: #1a1a5e
   - **المحاذاة**: center
   - **النص**: "بمدة تدريبية قدرها [عدد بالعربي] ([عدد]) ساعة تدريبية"

4. **التاريخ**
   - **الموضع الحالي**: X: 300, Y: 1600 (تقريبي)
   - **الحجم الحالي**: 20px
   - **اللون الحالي**: #1a1a5e
   - **المحاذاة**: left
   - **النص**: "التاريخ: [تاريخ بالعربي]"

5. **QR Code** (اختياري)
   - **الموضع**: الزاوية اليمنى السفلى
   - **الحجم**: 200x200 بكسل
   - **المحاذاة**: bottom-right, offset: 50, 50

---

### الخطوة 4: تحديث المواضع في الكود

#### 4.1. فتح ملف الكود
```
app/Http/Services/System/CertificateService.php
```

#### 4.2. تعديل دالة `generateCertificateImage`
حدد المواضع الدقيقة بناءً على القالب الفعلي:

```php
// مثال على التعديل:
$image->text($userName, $xPosition, $yPosition, function ($font) use ($fontPath) {
    if ($fontPath) {
        $font->filename($fontPath);
    }
    $font->size(48); // حجم الخط
    $font->color('#1a1a5e'); // لون النص
    $font->align('center'); // المحاذاة
    $font->valign('middle'); // المحاذاة العمودية
});
```

---

### الخطوة 5: اختبار توليد الشهادة

#### 5.1. اختبار توليد شهادة تجريبية
```bash
# إنشاء شهادة تجريبية
php artisan tinker
```

```php
$service = app(\App\Http\Services\System\CertificateService::class);
$certificate = \App\Models\System\Certificate::find(11); // أو أي شهادة
$imagePath = $service->generateCertificateImage($certificate);
echo "تم إنشاء الشهادة في: " . $imagePath;
```

#### 5.2. فتح الشهادة المولدة
افتح الملف في:
```
storage/app/public/certificates/{certificate_code}.png
```

#### 5.3. التحقق من:
- ✅ اسم المستخدم في الموضع الصحيح
- ✅ اسم الكورس في الموضع الصحيح
- ✅ مدة التدريب في الموضع الصحيح
- ✅ التاريخ في الموضع الصحيح
- ✅ QR Code في الموضع الصحيح (إذا كان PNG)
- ✅ الخطوط العربية تظهر بشكل صحيح
- ✅ الألوان صحيحة
- ✅ المحاذاة صحيحة

---

### الخطوة 6: إعادة توليد الشهادات الموجودة (اختياري)

إذا كان لديك شهادات موجودة وترغب في إعادة توليد صورها:

```bash
# إعادة توليد صورة شهادة محددة
php artisan certificates:regenerate-image {certificate_id}

# إعادة توليد صور جميع الشهادات
php artisan certificates:regenerate-image --all
```

**ملاحظة**: هذا Command يجب إنشاؤه أولاً (سيتم إنشاؤه لاحقاً).

---

## 🛠️ الأدوات المطلوبة

### البرامج:
1. **برنامج تصميم**: Photoshop, GIMP, Figma (لتحديد المواضع)
2. **محرر نصوص**: VS Code, PHPStorm (لتعديل الكود)
3. **متصفح**: Chrome, Firefox (لفتح الشهادة المولدة)

### الملفات:
1. **صورة القالب**: `certificate-template.png`
2. **الخطوط العربية**: `.ttf` أو `.otf` files

---

## 📝 ملاحظات مهمة

### 1. نظام الإحداثيات
- **X**: يبدأ من اليسار (0 = أقصى اليسار)
- **Y**: يبدأ من الأعلى (0 = أقصى الأعلى)
- **العرض الكامل للصورة**: يمكن الحصول عليه من `$image->width()`
- **الارتفاع الكامل للصورة**: يمكن الحصول عليه من `$image->height()`

### 2. الخطوط العربية
- **المشكلة**: GD driver في PHP قد لا يدعم الخطوط العربية بشكل جيد
- **الحل البديل**: استخدام خطوط تدعم Unicode مثل:
  - Arial Unicode MS
  - Noto Sans Arabic
  - Amiri
  - Tahoma

### 3. QR Code
- **المشكلة الحالية**: QR Code بصيغة SVG (لا يمكن دمجه مباشرة في PNG)
- **الحلول**:
  - **الخيار 1**: تثبيت imagick extension لتحويل SVG إلى PNG
  - **الخيار 2**: استخدام مكتبة تحويل SVG إلى PNG (مثل `enshrined/svg-sanitize`)
  - **الخيار 3**: توليد QR Code مباشرة كـ PNG (يحتاج imagick)
  - **الخيار 4**: عرض QR Code بشكل منفصل (في صفحة الويب)

### 4. الأداء
- توليد صورة الشهادة عملية مكلفة
- يُفضل استخدام **Queue Jobs** لتوليد الشهادات في الخلفية
- يمكن إضافة **Cache** للشهادات المولدة

---

## 🔧 الأكواد المرجعية

### الحصول على أبعاد الصورة:
```php
$image = $manager->read($templatePath);
$width = $image->width();  // عرض الصورة
$height = $image->height(); // ارتفاع الصورة
$centerX = $width / 2;     // منتصف الصورة أفقي
$centerY = $height / 2;    // منتصف الصورة عمودي
```

### كتابة نص في منتصف الصورة:
```php
$image->text($text, $centerX, $centerY, function ($font) use ($fontPath) {
    if ($fontPath) {
        $font->filename($fontPath);
    }
    $font->size(48);
    $font->color('#1a1a5e');
    $font->align('center');
    $font->valign('middle');
});
```

### كتابة نص بمحاذاة يسار:
```php
$image->text($text, $x, $y, function ($font) use ($fontPath) {
    if ($fontPath) {
        $font->filename($fontPath);
    }
    $font->size(20);
    $font->color('#1a1a5e');
    $font->align('left');
    $font->valign('top');
});
```

### دمج QR Code (PNG فقط):
```php
if ($certificate->qr_code && str_ends_with($certificate->qr_code, '.png')) {
    $qrCodePath = storage_path('app/public/' . $certificate->qr_code);
    if (file_exists($qrCodePath)) {
        $qrCodeImage = $manager->read($qrCodePath);
        $qrCodeImage->resize(200, 200);
        $image->place($qrCodeImage, 'bottom-right', 50, 50);
    }
}
```

---

## ✅ قائمة التحقق

قبل اعتبار صورة الشهادة جاهزة:

- [ ] صورة القالب موجودة في `storage/app/public/certificates/templates/`
- [ ] الخطوط العربية موجودة في `storage/fonts/`
- [ ] المواضع محددة بشكل دقيق في الكود
- [ ] تم اختبار توليد شهادة تجريبية
- [ ] جميع النصوص تظهر بشكل صحيح
- [ ] الخطوط العربية تعمل بشكل صحيح
- [ ] الألوان صحيحة
- [ ] المحاذاة صحيحة
- [ ] QR Code يظهر (إذا كان PNG)
- [ ] الصورة النهائية جاهزة للطباعة (300 DPI)

---

## 🆘 حل المشاكل الشائعة

### المشكلة 1: الخطوط العربية لا تظهر
**الحل**: تأكد من:
- الخط موجود في `storage/fonts/`
- اسم الملف صحيح
- الخط يدعم العربية (Unicode)

### المشكلة 2: النصوص في مواضع خاطئة
**الحل**: استخدم برنامج تصميم لتحديد المواضع الدقيقة (X, Y)

### المشكلة 3: QR Code لا يظهر في الصورة
**الحل**: 
- تحويل SVG إلى PNG أولاً
- أو تثبيت imagick extension

### المشكلة 4: الألوان غير صحيحة
**الحل**: تحقق من HEX code للألوان في الكود

---

## 📞 الدعم

للأسئلة أو المشاكل، يرجى التواصل مع فريق التطوير.
