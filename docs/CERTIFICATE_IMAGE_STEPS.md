# خطوات العمل على صورة الشهادة - دليل سريع

## 📋 الخطوات الأساسية

### ✅ الخطوة 1: رفع صورة القالب

1. **تحضير صورة القالب**
   - صيغة: PNG
   - حجم: 2400x1600 بكسل (أو حسب التصميم)
   - دقة: 300 DPI (للطباعة)

2. **رفع الصورة**
   ```bash
   # إنشاء المجلد
   mkdir -p storage/app/public/certificates/templates
   
   # نسخ صورة القالب إلى:
   storage/app/public/certificates/templates/certificate-template.png
   ```

3. **التحقق**
   ```bash
   # التأكد من وجود الملف
   ls -la storage/app/public/certificates/templates/certificate-template.png
   ```

---

### ✅ الخطوة 2: إعداد الخطوط العربية

1. **تحميل خطوط عربية**
   - مثال: Arial Unicode MS, Noto Sans Arabic, Amiri, Tahoma
   - الصيغة: `.ttf` أو `.otf`

2. **حفظ الخطوط**
   ```bash
   # إنشاء مجلد الخطوط
   mkdir -p storage/fonts
   
   # نسخ الخطوط (مثال):
   # cp arial.ttf storage/fonts/
   # cp arial-bold.ttf storage/fonts/
   ```

3. **التحقق**
   ```bash
   ls -la storage/fonts/
   ```

---

### ✅ الخطوة 3: تحديد المواضع الدقيقة

#### الطريقة الموصى بها:

1. **افتح صورة القالب** في برنامج تصميم (Photoshop, GIMP, Figma)

2. **أضف نصوص تجريبية** في المواضع المطلوبة:
   - اسم المستخدم: "أحمد محمد"
   - اسم الكورس: "التخطيط الاستراتيجي"
   - مدة التدريب: "بمدة تدريبية قدرها ثلاثون (30) ساعة تدريبية"
   - التاريخ: "التاريخ: 10 يناير 2026"

3. **حدد المواضع الدقيقة**:
   - اضغط على النص في برنامج التصميم
   - اقرأ الإحداثيات X, Y من Properties Panel
   - سجل الألوان (HEX codes)
   - سجل أحجام الخطوط

#### مثال على المواضع:

```
اسم المستخدم:
  X: 1200 (منتصف الصورة تقريباً)
  Y: 600
  Font Size: 48px
  Color: #1a1a5e
  Alignment: center

اسم الكورس:
  X: 1200
  Y: 800
  Font Size: 36px
  Color: #D4A017
  Alignment: center

مدة التدريب:
  X: 1200
  Y: 1000
  Font Size: 24px
  Color: #1a1a5e
  Alignment: center

التاريخ:
  X: 300 (من اليسار)
  Y: 1600 (من الأعلى)
  Font Size: 20px
  Color: #1a1a5e
  Alignment: left

QR Code:
  Position: bottom-right
  Offset: 50px from right, 50px from bottom
  Size: 200x200 pixels
```

---

### ✅ الخطوة 4: تحديث الكود

1. **افتح الملف**:
   ```
   app/Http/Services/System/CertificateService.php
   ```

2. **ابحث عن دالة** `generateCertificateImage` (السطر ~332)

3. **حدّث المواضع** بناءً على القياسات من الخطوة 3:

```php
// مثال على التعديل:
// 1. اسم المستخدم
$image->text($userName, 1200, 600, function ($font) use ($fontPath) {
    if ($fontPath) {
        $font->filename($fontPath);
    }
    $font->size(48);           // ← غيّر حسب الحجم المطلوب
    $font->color('#1a1a5e');   // ← غيّر حسب اللون المطلوب
    $font->align('center');
    $font->valign('middle');
});

// 2. اسم الكورس
$image->text($courseTitle, 1200, 800, function ($font) use ($fontPath) {
    if ($fontPath) {
        $font->filename($fontPath);
    }
    $font->size(36);           // ← غيّر حسب الحجم المطلوب
    $font->color('#D4A017');   // ← غيّر حسب اللون المطلوب
    $font->align('center');
    $font->valign('middle');
});

// ... إلخ
```

---

### ✅ الخطوة 5: اختبار توليد الشهادة

#### الطريقة 1: اختبار مباشر

```bash
php artisan tinker
```

```php
// جلب شهادة موجودة
$certificate = App\Models\System\Certificate::find(11); // أو أي ID

// توليد الصورة
$service = app(\App\Http\Services\System\CertificateService::class);
$imagePath = $service->generateCertificateImage($certificate);

echo "تم إنشاء الشهادة في: storage/app/public/{$imagePath}";
```

#### الطريقة 2: استخدام Command

```bash
# إعادة توليد صورة شهادة محددة
php artisan certificates:regenerate-image 11

# إعادة توليد حتى لو كانت موجودة
php artisan certificates:regenerate-image 11 --force

# إعادة توليد جميع الشهادات
php artisan certificates:regenerate-image --all
```

#### الطريقة 3: إصدار شهادة جديدة

```bash
# استدعاء API لإصدار شهادة جديدة (سيتم توليد الصورة تلقائياً)
POST /api/v1/admin/certificates/issue
{
  "user_id": 3,
  "course_id": 12,
  "level_id": 36
}
```

---

### ✅ الخطوة 6: فحص النتيجة

1. **افتح الملف المولد**:
   ```
   storage/app/public/certificates/{certificate_code}.png
   ```

2. **تحقق من**:
   - ✅ اسم المستخدم في الموضع الصحيح
   - ✅ اسم الكورس في الموضع الصحيح
   - ✅ مدة التدريب في الموضع الصحيح
   - ✅ التاريخ في الموضع الصحيح
   - ✅ الخطوط العربية تظهر بشكل صحيح
   - ✅ الألوان صحيحة
   - ✅ المحاذاة صحيحة
   - ✅ QR Code يظهر (إذا كان PNG)

3. **إذا كانت المواضع خاطئة**:
   - ارجع إلى الخطوة 3
   - حدد المواضع بدقة أكبر
   - عدّل الكود في الخطوة 4
   - كرر الخطوة 5

---

## 🎯 ملخص سريع

```bash
# 1. رفع صورة القالب
mkdir -p storage/app/public/certificates/templates
# ثم ارفع certificate-template.png إلى المجلد

# 2. إعداد الخطوط
mkdir -p storage/fonts
# ثم ارفع الخطوط العربية (.ttf) إلى المجلد

# 3. تحديث المواضع في الكود
# فتح: app/Http/Services/System/CertificateService.php
# تعديل: generateCertificateImage() method

# 4. اختبار
php artisan certificates:regenerate-image 11 --force

# 5. فحص النتيجة
# افتح: storage/app/public/certificates/{certificate_code}.png
```

---

## 📁 الملفات المطلوبة

```
storage/
  app/
    public/
      certificates/
        templates/
          certificate-template.png  ← صورة القالب (مطلوبة)
        qr/
          *.svg                    ← QR Codes
        *.png                      ← صور الشهادات المولدة
  fonts/
    arial.ttf                      ← خط عربي (مطلوب)
    arial-bold.ttf                 ← خط عربي عريض (اختياري)
```

---

## 🔧 الأوامر المفيدة

```bash
# التحقق من وجود القالب
ls -la storage/app/public/certificates/templates/

# التحقق من وجود الخطوط
ls -la storage/fonts/

# إعادة توليد صورة واحدة
php artisan certificates:regenerate-image {id}

# إعادة توليد جميع الصور
php artisan certificates:regenerate-image --all

# إعادة توليد مع استبدال الموجود
php artisan certificates:regenerate-image --all --force

# فحص شهادة محددة
php artisan certificates:check-level 36 3
```

---

## ⚠️ ملاحظات مهمة

1. **صورة القالب مطلوبة**: بدونها لن تعمل الشهادات
2. **الخطوط العربية مهمة**: بدونها لن تظهر النصوص بشكل صحيح
3. **المواضع دقيقة**: يجب قياسها من برنامج التصميم
4. **QR Code SVG**: حالياً لا يمكن دمجه في PNG بدون imagick
5. **الألوان**: استخدم HEX codes دقيقة من التصميم

---

## 🆘 حل المشاكل

### المشكلة: "صورة القالب غير موجودة"
**الحل**: 
```bash
mkdir -p storage/app/public/certificates/templates
# ثم ارفع certificate-template.png
```

### المشكلة: "الخطوط لا تظهر"
**الحل**: 
```bash
mkdir -p storage/fonts
# ثم ارفع خطوط عربية (.ttf)
```

### المشكلة: "المواضع خاطئة"
**الحل**: استخدم برنامج تصميم لقياس المواضع الدقيقة

### المشكلة: "QR Code لا يظهر في الصورة"
**الحل**: حالياً QR Code بصيغة SVG، يحتاج تحويل إلى PNG أولاً (يحتاج imagick)

---

## 📞 الخطوات التالية

بعد إعداد كل شيء:
1. ✅ اختبر توليد شهادة تجريبية
2. ✅ تأكد من أن جميع النصوص في المواضع الصحيحة
3. ✅ أعد توليد الشهادات الموجودة (إذا لزم)
4. ✅ اختبر عرض الشهادة في الواجهة
