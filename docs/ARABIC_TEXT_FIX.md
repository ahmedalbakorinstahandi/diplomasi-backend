# حل جذري لمشكلة النص العربي المتخربط

## المشكلة

النص العربي يظهر بشكل "سباغيتي" (حروف غير موصولة ومقلوبة) لأن:
- **GD Driver**: ❌ لا يدعم Arabic shaping بشكل جيد
- **Imagick بدون Pango**: ❌ لا يدعم Arabic shaping بشكل جيد
- **Imagick مع Pango**: ✅✅ يدعم العربية بشكل ممتاز

---

## الحل الجذري (الأفضل): تثبيت Pango

```bash
# على السيرفر
ssh root@45.132.241.51

# 1. تثبيت Pango و Cairo
apt-get update
apt-get install libpango1.0-dev libcairo2-dev libharfbuzz-dev -y

# 2. التحقق من دعم Pango في ImageMagick
convert -version | grep pangocairo

# 3. إعادة تشغيل PHP
systemctl restart php8.2-fpm
```

**بعد هذا، الكود الحالي سيستخدم Pango تلقائياً ويظهر النص العربي بشكل صحيح!**

---

## الحل البديل: استخدام Node.js Canvas

إذا لم يكن تثبيت Pango ممكناً، يمكن استخدام Node.js Canvas الذي يدعم العربية بشكل ممتاز:

### 1. تثبيت Node.js على السيرفر

```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt-get install -y nodejs
```

### 2. إنشاء script Node.js

```javascript
// render-certificate.js
const { createCanvas, loadImage, registerFont } = require('canvas');
const fs = require('fs');

registerFont('./storage/app/fonts/itfHuwiyaDisplay-Regular.otf', { family: 'ArabicFont' });

const width = 1200;
const height = 850;
const canvas = createCanvas(width, height);
const ctx = canvas.getContext('2d');

// خلفية بيضاء
ctx.fillStyle = '#FFFFFF';
ctx.fillRect(0, 0, width, height);

// إعداد النص العربي
ctx.font = '32px ArabicFont';
ctx.fillStyle = '#1a1a5e';
ctx.textAlign = 'center';
ctx.textBaseline = 'top';
ctx.direction = 'rtl'; // RTL مهم جداً!

// كتابة النص
const text = process.argv[2] || 'السلام عليكم';
ctx.fillText(text, width / 2, 100);

// حفظ الصورة
const buffer = canvas.toBuffer('image/png');
fs.writeFileSync(process.argv[3] || 'output.png', buffer);
```

### 3. استدعاء من PHP

```php
exec("node render-certificate.js '$arabicText' '$outputPath'");
```

---

## الحل الحالي (المحسّن)

تم تحديث `ArabicTextRenderer::prepareArabicText()` لمحاولة عكس ترتيب النص يدوياً، لكن هذا **ليس حلاً مثالياً**.

**للحل الجذري الحقيقي: ثبّت Pango على السيرفر!**

---

## التحقق من نجاح التثبيت

بعد تثبيت Pango:

```bash
# التحقق من Pango
convert -version | grep pangocairo

# إذا ظهر: "pangocairo" = ✅ نجح التثبيت
```

بعد ذلك، افتح صفحة الشهادة - النص العربي سيظهر بشكل صحيح!
