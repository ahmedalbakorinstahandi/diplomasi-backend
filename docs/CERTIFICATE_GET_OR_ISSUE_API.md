# Certificate Get or Issue API - التحقق من الشهادة وإصدارها تلقائياً

## نظرة عامة

هذا الـ API يتيح الحصول على شهادة موجودة أو إصدار شهادة جديدة تلقائياً إذا كان المستخدم مؤهلاً.

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

### ✅ الحالة 1: الشهادة موجودة مسبقاً

**Status Code:** `200 OK`

```json
{
  "status": true,
  "message": "الشهادة موجودة مسبقاً",
  "data": {
    "certificate_status": "already_exists",
    "message": "الشهادة موجودة مسبقاً",
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
- الشهادة موجودة مسبقاً ولم يتم إصدار شهادة جديدة
- يتم إرجاع معلومات الشهادة الموجودة بنفس صيغة `show`
- حقل `certificate_status` يخبرك أن الشهادة كانت موجودة مسبقاً

---

### ✅ الحالة 2: تم إصدار شهادة جديدة

**Status Code:** `200 OK`

```json
{
  "status": true,
  "message": "تم إصدار الشهادة بنجاح",
  "data": {
    "certificate_status": "newly_issued",
    "message": "تم إصدار الشهادة بنجاح",
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
- تم التحقق من أهلية المستخدم بنجاح
- تم إصدار شهادة جديدة
- تم توليد الصورة والـ QR Code
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

### 2. المستخدم غير مسجل في الكورس

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

### 3. الكورس لم يكتمل بعد

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

### 4. تاريخ الإكمال غير موجود

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

### 5. بعض المستويات غير مكتملة

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

### 6. تم إصدار شهادة سابقة للكورس

**Status Code:** `400 Bad Request`

```json
{
  "status": false,
  "message": "تم إصدار شهادة سابقة لهذا الكورس"
}
```

**ملاحظة:** هذا لن يحدث عادةً لأن الـ API يتحقق من الشهادة الموجودة أولاً ويرجعها

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

### 13. فشل في إصدار الشهادة

**Status Code:** `500 Internal Server Error`

```json
{
  "status": false,
  "message": "فشل في إصدار الشهادة"
}
```

**متى يحدث:**
- خطأ داخلي أثناء عملية إصدار الشهادة

---

### 14. حدث خطأ أثناء معالجة الشهادة

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
| `certificate_status` | string | حالة العملية: `already_exists` أو `newly_issued` |
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
  if (result.data.certificate_status === 'already_exists') {
    console.log('الشهادة موجودة مسبقاً');
  } else if (result.data.certificate_status === 'newly_issued') {
    console.log('تم إصدار شهادة جديدة');
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
  
  // رابط التحقق (للباركود)
  const verificationUrl = `https://api.diplomasi.com/certificates/${certificate.certificate_code}/verify`;
  
  // فتح صفحة التحقق
  window.open(verificationUrl, '_blank');
}
```

---

## Flow Chart - مخطط التدفق

```
START
  ↓
هل الشهادة موجودة مسبقاً؟
  ↓
YES → إرجاع الشهادة الموجودة (already_exists)
  ↓
NO → التحقق من الأهلية
  ↓
هل المستخدم مؤهل؟
  ↓
NO → إرجاع رسالة خطأ واضحة (400)
  ↓
YES → إصدار شهادة جديدة
  ↓
هل نجح الإصدار؟
  ↓
NO → إرجاع خطأ (500)
  ↓
YES → إرجاع الشهادة الجديدة (newly_issued)
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
if (result.data.status === 'newly_issued') {
  localStorage.setItem(
    `certificate_${certificate.certificate_code}`,
    JSON.stringify(certificate)
  );
}
```

---

## الخلاصة

هذا الـ API يوفر طريقة بسيطة وآمنة للحصول على الشهادات:
- ✅ رسائل خطأ واضحة ومفصلة لكل حالة
- ✅ إصدار تلقائي للشهادات عند الأهلية
- ✅ إرجاع الشهادة الموجودة إذا كانت مُصدرة مسبقاً
- ✅ معلومات كاملة عن الشهادة (رابط التحقق، الصورة، QR Code)
- ✅ سهولة الاستخدام في التطبيق

---

## Support

للأسئلة والدعم:
- Email: support@diplomasi.com
- Documentation: https://docs.diplomasi.com
