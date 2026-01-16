# طريقة توليد الشهادة باستخدام Blade Template

## الطريقة الجديدة (الأفضل)

تم إنشاء طريقة جديدة لتوليد صورة الشهادة باستخدام **Blade template** ثم تحويلها إلى PNG. هذه الطريقة **أفضل بكثير للعربية** لأنها تستخدم:

1. **mPDF** - يدعم العربية بشكل ممتاز
2. **Blade Template** - سهولة التعديل والتصميم
3. **HTML/CSS** - مرونة كاملة في التصميم

## كيف تعمل؟

1. **إنشاء HTML من Blade template** (`resources/views/certificates/image_template.blade.php`)
2. **تحويل HTML إلى PDF** باستخدام mPDF
3. **تحويل PDF إلى PNG** باستخدام Imagick

## التثبيت

```bash
# تثبيت mPDF
composer require mpdf/mpdf
```

## الملفات المطلوبة

1. **Blade Template**: `resources/views/certificates/image_template.blade.php`
2. **Service Method**: `CertificateService::generateCertificateImageFromBlade()`
3. **mPDF** package (يتم تثبيته عبر composer)

## الاستخدام

الكود يستخدم الطريقة الجديدة تلقائياً عند توليد شهادة جديدة:

```php
// في CertificateService::store()
$imagePath = $this->generateCertificateImageFromBlade($certificate);
```

## المميزات

✅ **دعم ممتاز للعربية** - مPDF يدعم Arabic shaping بشكل كامل  
✅ **سهولة التعديل** - تعديل HTML/CSS بدلاً من كود PHP معقد  
✅ **مرونة في التصميم** - استخدام CSS كامل  
✅ **جودة عالية** - دقة 300 DPI

## الخط العربي

الخط العربي (`itfHuwiyaDisplay-Regular.otf`) يتم تسجيله في mPDF تلقائياً إذا كان موجوداً في:
```
storage/app/fonts/itfHuwiyaDisplay-Regular.otf
```

## ملاحظات

- تتطلب **Imagick extension** لتحويل PDF إلى PNG
- يتم حذف ملفات PDF المؤقتة تلقائياً بعد التحويل
- الطريقة القديمة (`generateCertificateImage`) محفوظة للرجوع إذا لزم الأمر
