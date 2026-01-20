# Certificate Verify and Generate Image API - التحقق من الشهادة وتوليد الصورة

## نظرة عامة

هذا الـ API يتيح التحقق من وجود شهادة للمستخدم وتوليد صورة PNG لها إذا لم تكن موجودة. يعمل بنفس طريقة رابط التحقق Web.

## Endpoint

```
POST /api/v1/admin/certificates/get-or-issue
```

## المصادقة

يتطلب:
- Bearer Token
- صلاحيات Admin

## Request Body

```json
{
  "user_id": 1,
  "course_id": 5,
  "level_id": 12  // اختياري - للشهادات على مستوى Level
}
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `user_id` | integer | **نعم** | معرّف المستخدم |
| `course_id` | integer | **نعم** | معرّف الكورس |
| `level_id` | integer | لا | معرّف المستوى (إذا كانت الشهادة لمستوى معين) |

---

## Response Cases - الحالات المختلفة

### ✅ الحالة 1: الشهادة موجودة والصورة جاهزة

**Status Code:** `200 OK`

```json
{
  "status": true,
  "message": "الشهادة جاهزة",
  "data": {
    "certificate_status": "certificate_ready",
    "message": "الشهادة جاهزة",
    "id": 42,
    "certificate_code": "CERT-20260116-1-5-ABC123",
    "user_id": 1,
    "course_id": 5,
    "level_id": null,
    "issued_at": "2026-01-16 10:30:00",
    "revoked_at": null,
    "status": "active",
    "template_path": "certificates/templates/certificate-template.png",
    "qr_code": "qr/certificates/CERT-20260116-1-5-ABC123.png",
    "image_url": "certificates/CERT-20260116-1-5-ABC123.png",
    "created_at": "2026-01-16 10:30:00",
    "updated_at": "2026-01-16 10:30:05",
    "user": {
      "id": 1,
      "first_name": "أحمد",
      "last_name": "محمد",
      "email": "ahmed@example.com",
      "phone": "+966501234567"
    },
    "course": {
      "id": 5,
      "title": "دورة البرمجة المتقدمة",
      "description": "تعلم البرمجة المتقدمة",
      "cover_image": "courses/course-5.jpg"
    },
    "level": null
  }
}
```

**الوصف:**
- الشهادة موجودة والصورة جاهزة للعرض
- لا حاجة لتوليد صورة جديدة
- يتم إرجاع معلومات الشهادة الكاملة بنفس صيغة `show`

---

### ✅ الحالة 2: تم توليد صورة الشهادة الآن

**Status Code:** `200 OK`

```json
{
  "status": true,
  "message": "تم توليد صورة الشهادة بنجاح",
  "data": {
    "certificate_status": "image_generated",
    "message": "تم توليد صورة الشهادة بنجاح",
    "id": 43,
    "certificate_code": "CERT-20260116-1-5-XYZ789",
    "user_id": 1,
    "course_id": 5,
    "level_id": 12,
    "issued_at": "2026-01-16 11:45:00",
    "revoked_at": null,
    "status": "active",
    "template_path": "certificates/templates/certificate-template.png",
    "qr_code": "qr/certificates/CERT-20260116-1-5-XYZ789.png",
    "image_url": "certificates/CERT-20260116-1-5-XYZ789.png",
    "created_at": "2026-01-16 11:45:00",
    "updated_at": "2026-01-16 11:45:05",
    "user": {
      "id": 1,
      "first_name": "أحمد",
      "last_name": "محمد",
      "email": "ahmed@example.com",
      "phone": "+966501234567"
    },
    "course": {
      "id": 5,
      "title": "دورة البرمجة المتقدمة",
      "description": "تعلم البرمجة المتقدمة",
      "cover_image": "courses/course-5.jpg"
    },
    "level": {
      "id": 12,
      "title": "المستوى المتقدم",
      "description": "المستوى الثالث",
      "order": 3
    }
  }
}
```

**الوصف:**
- الشهادة موجودة لكن الصورة PNG لم تكن موجودة
- تم توليد الصورة بنجاح من البيانات
- البيانات بنفس صيغة `show` مع إضافة `certificate_status`

---

## ❌ Error Cases - حالات الخطأ

### 1. بيانات غير صحيحة (Validation Error)

**Status Code:** `422 Unprocessable Entity`

```json
{
  "status": false,
  "message": "The given data was invalid.",
  "errors": {
    "user_id": ["The user_id field is required."],
    "course_id": ["The selected course_id is invalid."]
  }
}
```

**الأسباب المحتملة:**
- `user_id` مفقود أو غير موجود في قاعدة البيانات
- `course_id` مفقود أو غير موجود في قاعدة البيانات
- `level_id` غير موجود في قاعدة البيانات (إذا تم إرساله)

---

### 2. الشهادة لم يتم إصدارها بعد

**Status Code:** `404 Not Found`

```json
{
  "status": false,
  "message": "الشهادة لم يتم إصدارها بعد"
}
```

**متى يحدث:**
- المستخدم مؤهل لكن الشهادة لم تُصدر له بعد
- يجب إصدار الشهادة من لوحة التحكم أو تلقائياً عند إكمال الكورس

---

### 3. المستخدم غير مسجل في الكورس

**Status Code:** `400 Bad Request`

```json
{
  "status": false,
  "message": "المستخدم غير مسجل في هذا الكورس"
}
```

**متى يحدث:**
- المستخدم لم يسجل في الكورس المطلوب

---

### 4. الكورس لم يكتمل بعد

**Status Code:** `400 Bad Request`

```json
{
  "status": false,
  "message": "الكورس لم يكتمل بعد"
}
```

**متى يحدث:**
- المستخدم مسجل في الكورس ولكن لم يكمله بعد
- حالة الكورس ليست `completed`

---

### 5. تاريخ الإكمال غير موجود

**Status Code:** `400 Bad Request`

```json
{
  "status": false,
  "message": "تاريخ الإكمال غير موجود"
}
```

**متى يحدث:**
- الكورس مكتمل ولكن `completed_at` فارغ
- مشكلة في البيانات

---

### 6. بعض المستويات غير مكتملة

**Status Code:** `400 Bad Request`

```json
{
  "status": false,
  "message": "بعض المستويات غير مكتملة"
}
```

**متى يحدث:**
- عند طلب شهادة للكورس (بدون level_id)
- الكورس يحتوي على مستويات لها شهادات
- المستخدم لم يكمل جميع المستويات

---

### 7. المستوى غير موجود

**Status Code:** `400 Bad Request`

```json
{
  "status": false,
  "message": "المستوى غير موجود"
}
```

**متى يحدث:**
- تم إرسال `level_id` غير موجود في قاعدة البيانات

---

### 8. المستوى لا ينتمي لهذا الكورس

**Status Code:** `400 Bad Request`

```json
{
  "status": false,
  "message": "المستوى لا ينتمي لهذا الكورس"
}
```

**متى يحدث:**
- `level_id` موجود ولكن ينتمي لكورس آخر غير `course_id` المُرسل

---

### 9. هذا المستوى لا يحتوي على شهادة

**Status Code:** `400 Bad Request`

```json
{
  "status": false,
  "message": "هذا المستوى لا يحتوي على شهادة"
}
```

**متى يحدث:**
- المستوى لا يحتوي على شهادة (`has_certificate = false`)

---

### 10. المستخدم غير مسجل في هذا المستوى

**Status Code:** `400 Bad Request`

```json
{
  "status": false,
  "message": "المستخدم غير مسجل في هذا المستوى"
}
```

**متى يحدث:**
- المستخدم لم يسجل في المستوى المطلوب

---

### 11. المستوى لم يكتمل بعد

**Status Code:** `400 Bad Request`

```json
{
  "status": false,
  "message": "المستوى لم يكتمل بعد"
}
```

**متى يحدث:**
- المستخدم مسجل في المستوى ولكن لم يكمله بعد

---

### 12. تاريخ إكمال المستوى غير موجود

**Status Code:** `400 Bad Request`

```json
{
  "status": false,
  "message": "تاريخ إكمال المستوى غير موجود"
}
```

**متى يحدث:**
- المستوى مكتمل ولكن `completed_at` فارغ

---

### 13. حدث خطأ أثناء معالجة الشهادة

**Status Code:** `500 Internal Server Error`

```json
{
  "status": false,
  "message": "حدث خطأ أثناء معالجة الشهادة"
}
```

**متى يحدث:**
- خطأ غير متوقع أثناء معالجة الطلب

---

## Certificate Response Fields - حقول الشهادة

البيانات ترجع بنفس صيغة `/certificates/{id}` (show) مع إضافة حقلين:

| Field | Type | Description |
|-------|------|-------------|
| `certificate_status` | string | حالة العملية: `certificate_ready` أو `image_generated` |
| `message` | string | رسالة توضيحية بالعربية |
| `id` | integer | معرّف الشهادة |
| `certificate_code` | string | كود الشهادة الفريد |
| `user_id` | integer | معرّف المستخدم |
| `course_id` | integer | معرّف الكورس |
| `level_id` | integer\|null | معرّف المستوى |
| `issued_at` | string | تاريخ الإصدار (Y-m-d H:i:s) |
| `revoked_at` | string\|null | تاريخ الإلغاء |
| `status` | string | حالة الشهادة (active, revoked) |
| `template_path` | string | مسار قالب الشهادة |
| `qr_code` | string | مسار QR Code |
| `image_url` | string | مسار صورة الشهادة |
| `user` | object | معلومات المستخدم كاملة |
| `course` | object | معلومات الكورس كاملة |
| `level` | object\|null | معلومات المستوى كاملة |

**ملاحظة مهمة:** البيانات ترجع بنفس الصيغة تماماً كما في endpoint الـ `show` مما يسهل التحديث المباشر في التطبيق.

---

## استخدام الـ API في التطبيق

### مثال 1: طلب شهادة كورس

```javascript
// طلب شهادة لكورس مكتمل
const response = await fetch('https://api.diplomasi.com/api/v1/admin/certificates/get-or-issue', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    user_id: 1,
    course_id: 5
  })
});

const result = await response.json();

if (result.status) {
  // التحقق من حالة الشهادة
  if (result.data.certificate_status === 'certificate_ready') {
    console.log('الشهادة جاهزة');
  } else if (result.data.certificate_status === 'image_generated') {
    console.log('تم توليد صورة الشهادة');
  }
  
  // تحديث مباشر - البيانات بنفس صيغة show
  updateCertificateInState(result.data);
  
  // الوصول للبيانات
  console.log('كود الشهادة:', result.data.certificate_code);
  console.log('اسم المستخدم:', result.data.user.first_name);
  console.log('عنوان الكورس:', result.data.course.title);
} else {
  // عرض رسالة الخطأ
  alert(result.message);
}
```

### مثال 2: طلب شهادة مستوى

```javascript
// طلب شهادة لمستوى مكتمل
const response = await fetch('https://api.diplomasi.com/api/v1/admin/certificates/get-or-issue', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    user_id: 1,
    course_id: 5,
    level_id: 12
  })
});

const result = await response.json();

if (result.status) {
  // البيانات جاهزة للتحديث المباشر
  const certificate = result.data;
  
  // تحديث State مباشرة (نفس صيغة show)
  dispatch(setCertificate(certificate));
  
  // عرض الصورة
  if (certificate.image_url) {
    showCertificateImage(certificate.image_url);
  }
  
  // إذا تم توليد الصورة الآن
  if (certificate.certificate_status === 'image_generated') {
    showToast('تم توليد صورة الشهادة بنجاح');
  }
}
```

---

## Flow Chart - مخطط التدفق

```
START
  ↓
البحث عن الشهادة
  ↓
هل الشهادة موجودة؟
  ↓
NO → التحقق من الأهلية
  ↓
هل المستخدم مؤهل؟
  ↓
NO → إرجاع رسالة خطأ واضحة (400)
  ↓
YES → الشهادة لم تصدر بعد (404)
  ↓
YES (الشهادة موجودة) → هل الصورة PNG موجودة؟
  ↓
NO → توليد الصورة
  ↓
إرجاع الشهادة (image_generated)
  ↓
YES (الصورة موجودة) → إرجاع الشهادة (certificate_ready)
  ↓
END
```

---

## Best Practices - أفضل الممارسات

### 1. التعامل مع الأخطاء
```javascript
try {
  const response = await fetch(API_URL, options);
  const result = await response.json();
  
  if (!result.status) {
    // عرض رسالة الخطأ للمستخدم
    showErrorMessage(result.message);
  }
} catch (error) {
  // خطأ في الاتصال
  showErrorMessage('فشل الاتصال بالسيرفر');
}
```

### 2. التحقق من وجود الصورة
```javascript
if (certificate.image_exists) {
  // عرض صورة الشهادة
  showCertificateImage(certificate.image_url);
} else {
  // عرض رابط PDF أو رسالة
  showPDFLink(certificate.verification_url);
}
```

### 3. حفظ البيانات محلياً
```javascript
// حفظ معلومات الشهادة في Local Storage
if (result.data.certificate_status === 'image_generated') {
  localStorage.setItem(
    `certificate_${certificate.certificate_code}`,
    JSON.stringify(certificate)
  );
  showToast('تم توليد صورة الشهادة بنجاح');
}
```

---

## الخلاصة

هذا الـ API يوفر طريقة بسيطة وآمنة للتحقق من الشهادات:
- ✅ رسائل خطأ واضحة ومفصلة لكل حالة
- ✅ التحقق من وجود الشهادة وتوليد الصورة تلقائياً
- ✅ يعمل بنفس طريقة رابط التحقق Web
- ✅ الاعتماد على صورة PNG فقط (لا يوجد PDF)
- ✅ معلومات كاملة عن الشهادة بنفس صيغة show
- ✅ سهولة الاستخدام في التطبيق

---

## Support

للأسئلة والدعم:
- Email: support@diplomasi.com
- Documentation: https://docs.diplomasi.com
