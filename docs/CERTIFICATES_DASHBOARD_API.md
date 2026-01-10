# دليل API الشهادات للداشبورد

هذا الدليل يشرح بالتفصيل كيفية استخدام APIs الخاصة بالشهادات في لوحة التحكم (Dashboard) للمسؤولين.

## جدول المحتويات
- [المقدمة](#المقدمة)
- [المصادقة (Authentication)](#المصادقة-authentication)
- [APIs المتاحة](#apis-المتاحة)
  - [1. قائمة جميع الشهادات](#1-قائمة-جميع-الشهادات)
  - [2. تفاصيل شهادة محددة](#2-تفاصيل-شهادة-محددة)
  - [3. إصدار شهادة يدوياً](#3-إصدار-شهادة-يدوياً)
  - [4. إلغاء شهادة](#4-إلغاء-شهادة)
- [معالجة الأخطاء](#معالجة-الأخطاء)
- [توصيات UI/UX](#توصيات-uiux)
- [أمثلة كود](#أمثلة-كود)

---

## المقدمة

لوحة التحكم تسمح للمسؤولين بـ:
- عرض جميع الشهادات الصادرة في النظام
- إصدار شهادات يدوياً للمستخدمين
- إلغاء/إلغاء تفعيل الشهادات
- مراقبة وإدارة الشهادات

### أنواع الشهادات:
1. **شهادة الكورس**: عند إكمال الكورس بالكامل (level_id = null)
2. **شهادة المستوى**: عند إكمال مستوى محدد يحتوي على شهادة (level_id محدد)

### الصلاحيات المطلوبة:
- `certificate.view` - عرض الشهادات
- `certificate.issue` - إصدار شهادات
- `certificate.revoke` - إلغاء شهادات

---

## المصادقة (Authentication)

جميع APIs التالية تتطلب:
- **Header**: `Authorization: Bearer {admin_token}`
- **Context Header**: `X-Context: dashboard` (للوحة التحكم)
- **Admin Middleware**: يجب أن يكون المستخدم مسؤولاً

### مثال:
```http
GET /api/v1/admin/certificates
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
X-Context: dashboard
```

---

## APIs المتاحة

### 1. قائمة جميع الشهادات

**Endpoint**: `GET /api/v1/admin/certificates`

**المصادقة**: مطلوبة (Admin + `certificate.view`)

**الوصف**: جلب قائمة بجميع الشهادات في النظام مع إمكانية الفلترة والبحث.

**Query Parameters** (اختيارية):
```
per_page: number (default: 20) - عدد النتائج في الصفحة
page: number (default: 1) - رقم الصفحة
sort_field: string (default: 'issued_at') - حقل الترتيب (issued_at, created_at, id, certificate_code)
sort_order: string (default: 'desc') - اتجاه الترتيب (asc, desc)
user_id: number (optional) - فلترة حسب المستخدم
course_id: number (optional) - فلترة حسب الكورس
level_id: number (optional) - فلترة حسب المستوى
search: string (optional) - بحث في certificate_code
issued_at_from: date (optional) - فلترة حسب تاريخ الإصدار من
issued_at_to: date (optional) - فلترة حسب تاريخ الإصدار إلى
```

**Request Example**:
```http
GET /api/v1/admin/certificates?per_page=20&page=1&sort_field=issued_at&sort_order=desc&course_id=3&user_id=5
Authorization: Bearer {admin_token}
X-Context: dashboard
```

**Response Success (200)**:
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "user_id": 5,
        "course_id": 3,
        "level_id": null,
        "certificate_code": "CERT-20260110132840-5-3-0-ABC123",
        "issued_at": "2026-01-10T13:28:40.000000Z",
        "qr_code": "https://example.com/storage/certificates/qr/CERT-20260110132840-5-3-0-ABC123.png",
        "pdf_url": null,
        "image_url": "https://example.com/storage/certificates/CERT-20260110132840-5-3-0-ABC123.png",
        "template_path": "https://example.com/storage/certificates/templates/certificate-template.png",
        "verification_url": "https://example.com/api/v1/general/certificates/verify/CERT-20260110132840-5-3-0-ABC123",
        "download_url": "https://example.com/api/v1/user/certificates/1/download",
        "created_at": "2026-01-10T13:28:40.000000Z",
        "updated_at": "2026-01-10T13:28:40.000000Z",
        "user": {
          "id": 5,
          "first_name": "أحمد",
          "last_name": "محمد",
          "email": "ahmed@example.com",
          "phone": "0123456789"
        },
        "course": {
          "id": 3,
          "title": "دورة البرمجة المتقدمة",
          "description": "...",
          "image_url": "..."
        },
        "level": null
      },
      {
        "id": 2,
        "user_id": 5,
        "course_id": 3,
        "level_id": 10,
        "certificate_code": "CERT-20260110132845-5-3-10-XYZ789",
        "issued_at": "2026-01-10T13:28:45.000000Z",
        "image_url": "https://example.com/storage/certificates/CERT-20260110132845-5-3-10-XYZ789.png",
        "verification_url": "https://example.com/api/v1/general/certificates/verify/CERT-20260110132845-5-3-10-XYZ789",
        "user": {
          "id": 5,
          "first_name": "أحمد",
          "last_name": "محمد"
        },
        "course": {
          "id": 3,
          "title": "دورة البرمجة المتقدمة"
        },
        "level": {
          "id": 10,
          "title": "المستوى الأول: أساسيات البرمجة",
          "level_number": 1
        }
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8,
    "from": 1,
    "to": 20
  },
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  },
  "status": 200
}
```

**Response Error (401 Unauthorized)**:
```json
{
  "success": false,
  "message": "You are not logged in",
  "status": 401
}
```

**Response Error (403 Forbidden)**:
```json
{
  "success": false,
  "message": "messages.permission.error",
  "status": 403
}
```

**ما يجب أن يفعله Frontend**:
1. ✅ عرض جدول/قائمة بجميع الشهادات
2. ✅ إظهار معلومات المستخدم (الاسم، البريد الإلكتروني)
3. ✅ إظهار معلومات الكورس/المستوى
4. ✅ إظهار تاريخ الإصدار
5. ✅ إظهار نوع الشهادة (كورس / مستوى)
6. ✅ إضافة Filters:
   - فلترة حسب المستخدم
   - فلترة حسب الكورس
   - فلترة حسب المستوى
   - فلترة حسب تاريخ الإصدار
   - بحث في كود الشهادة
7. ✅ إضافة Sorting حسب الحقول المختلفة
8. ✅ دعم Pagination
9. ✅ إضافة Export to Excel/CSV (اختياري)
10. ✅ إضافة Bulk Actions (اختياري)

**UI Recommendation**:
```
┌─────────────────────────────────────────────────────────────────────┐
│  الشهادات                          [+ إصدار شهادة] [تصدير]        │
├─────────────────────────────────────────────────────────────────────┤
│  فلترة: [المستخدم ▼] [الكورس ▼] [المستوى ▼] [بحث...] [فلترة]   │
├─────────────────────────────────────────────────────────────────────┤
│  #  | المستخدم     | الكورس/المستوى    | النوع | التاريخ   | إجراءات│
├─────────────────────────────────────────────────────────────────────┤
│  1  | أحمد محمد   | دورة البرمجة      | كورس  | 10/1/2026 | [عرض]  │
│     |              |                   |       |           | [تحميل]│
│     |              |                   |       |           | [إلغاء]│
├─────────────────────────────────────────────────────────────────────┤
│  2  | أحمد محمد   | المستوى الأول     | مستوى | 10/1/2026 | [عرض]  │
│     |              | دورة البرمجة      |       |           | [تحميل]│
│     |              |                   |       |           | [إلغاء]│
├─────────────────────────────────────────────────────────────────────┤
│                         [الصفحة 1 من 8] [<] [>]                    │
└─────────────────────────────────────────────────────────────────────┘
```

---

### 2. تفاصيل شهادة محددة

**Endpoint**: `GET /api/v1/admin/certificates/{id}`

**المصادقة**: مطلوبة (Admin + `certificate.view`)

**الوصف**: جلب تفاصيل شهادة محددة مع جميع المعلومات المرتبطة.

**Path Parameters**:
- `id`: number (required) - معرف الشهادة

**Request Example**:
```http
GET /api/v1/admin/certificates/1
Authorization: Bearer {admin_token}
X-Context: dashboard
```

**Response Success (200)**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 5,
    "course_id": 3,
    "level_id": null,
    "certificate_code": "CERT-20260110132840-5-3-0-ABC123",
    "issued_at": "2026-01-10T13:28:40.000000Z",
    "qr_code": "https://example.com/storage/certificates/qr/CERT-20260110132840-5-3-0-ABC123.png",
    "pdf_url": null,
    "image_url": "https://example.com/storage/certificates/CERT-20260110132840-5-3-0-ABC123.png",
    "template_path": "https://example.com/storage/certificates/templates/certificate-template.png",
    "verification_url": "https://example.com/api/v1/general/certificates/verify/CERT-20260110132840-5-3-0-ABC123",
    "download_url": "https://example.com/api/v1/user/certificates/1/download",
    "created_at": "2026-01-10T13:28:40.000000Z",
    "updated_at": "2026-01-10T13:28:40.000000Z",
    "user": {
      "id": 5,
      "first_name": "أحمد",
      "last_name": "محمد",
      "email": "ahmed@example.com",
      "phone": "0123456789",
      "created_at": "2025-01-01T00:00:00.000000Z"
    },
    "course": {
      "id": 3,
      "title": "دورة البرمجة المتقدمة",
      "description": "دورة شاملة لتعلم البرمجة...",
      "image_url": "...",
      "created_at": "2025-01-01T00:00:00.000000Z"
    },
    "level": null
  },
  "status": 200
}
```

**Response Error (404 Not Found)**:
```json
{
  "success": false,
  "message": "messages.certificate.not_found",
  "status": 404
}
```

**ما يجب أن يفعله Frontend**:
1. ✅ عرض تفاصيل الشهادة في Modal أو صفحة منفصلة
2. ✅ إظهار جميع معلومات المستخدم
3. ✅ إظهار معلومات الكورس/المستوى
4. ✅ إظهار صورة الشهادة
5. ✅ إظهار QR Code
6. ✅ إظهار كود الشهادة للتحقق
7. ✅ إظهار روابط التحميل والتحقق
8. ✅ إضافة أزرار: تحميل، نسخ الرابط، إلغاء

**UI Recommendation**:
```
┌─────────────────────────────────────┐
│  تفاصيل الشهادة            [×]     │
├─────────────────────────────────────┤
│  معلومات المستخدم:                │
│  👤 الاسم: أحمد محمد               │
│  📧 البريد: ahmed@example.com      │
│  📱 الهاتف: 0123456789             │
│                                     │
│  معلومات الشهادة:                  │
│  📚 الكورس: دورة البرمجة المتقدمة │
│  🏷️  النوع: كورس                   │
│  📅 تاريخ الإصدار: 10/1/2026      │
│  🔑 الكود: CERT-2026...            │
│                                     │
│  [صورة الشهادة]                    │
│                                     │
│  [QR Code]                          │
│                                     │
│  [⬇️ تحميل] [📋 نسخ الرابط]       │
│  [🗑️ إلغاء الشهادة]                │
└─────────────────────────────────────┘
```

---

### 3. إصدار شهادة يدوياً

**Endpoint**: `POST /api/v1/admin/certificates/issue`

**المصادقة**: مطلوبة (Admin + `certificate.issue`)

**الوصف**: إصدار شهادة يدوياً لمستخدم معين في كورس محدد (أو مستوى محدد).

**Request Body**:
```json
{
  "user_id": 5,
  "course_id": 3,
  "level_id": null  // null للكورس، أو معرف المستوى للشهادة الخاصة بالمستوى
}
```

**Validation Rules**:
- `user_id`: required, exists:users,id
- `course_id`: required, exists:courses,id
- `level_id`: nullable, exists:levels,id (إذا كان محدداً، يجب أن ينتمي للكورس)

**Request Example**:
```http
POST /api/v1/admin/certificates/issue
Authorization: Bearer {admin_token}
X-Context: dashboard
Content-Type: application/json

{
  "user_id": 5,
  "course_id": 3,
  "level_id": null
}
```

**Response Success (201)**:
```json
{
  "success": true,
  "data": {
    "id": 15,
    "user_id": 5,
    "course_id": 3,
    "level_id": null,
    "certificate_code": "CERT-20260110132850-5-3-0-XYZ456",
    "issued_at": "2026-01-10T13:28:50.000000Z",
    "qr_code": "https://example.com/storage/certificates/qr/CERT-20260110132850-5-3-0-XYZ456.png",
    "image_url": "https://example.com/storage/certificates/CERT-20260110132850-5-3-0-XYZ456.png",
    "verification_url": "https://example.com/api/v1/general/certificates/verify/CERT-20260110132850-5-3-0-XYZ456",
    "download_url": "https://example.com/api/v1/user/certificates/15/download",
    "user": {
      "id": 5,
      "first_name": "أحمد",
      "last_name": "محمد"
    },
    "course": {
      "id": 3,
      "title": "دورة البرمجة المتقدمة"
    },
    "level": null
  },
  "message": "messages.certificate.issued",
  "status": 201
}
```

**Response Error (400 Bad Request) - المستخدم غير مؤهل**:
```json
{
  "success": false,
  "message": "المستخدم غير مؤهل للحصول على شهادة الكورس",
  "status": 400
}
```

**Response Error (400 Bad Request) - الشهادة موجودة مسبقاً**:
```json
{
  "success": false,
  "message": "تم إصدار شهادة سابقة لهذا الكورس",
  "status": 400
}
```

**Response Error (422 Validation Error)**:
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "user_id": ["The user id field is required."],
    "course_id": ["The course id field is required."]
  },
  "status": 422
}
```

**Response Error (403 Forbidden)**:
```json
{
  "success": false,
  "message": "messages.permission.error",
  "status": 403
}
```

**ما يجب أن يفعله Frontend**:
1. ✅ إنشاء Modal/Form لإصدار شهادة جديدة
2. ✅ إضافة حقل اختيار المستخدم (AutoComplete/Search)
3. ✅ إضافة حقل اختيار الكورس (AutoComplete/Dropdown)
4. ✅ إضافة حقل اختيار المستوى (AutoComplete/Dropdown) - اختياري
5. ✅ التحقق من الأهلية قبل الإرسال (اختياري - يمكن أن يتحقق Backend)
6. ✅ إظهار Loading أثناء الإصدار
7. ✅ عند النجاح:
   - إظهار رسالة نجاح
   - إعادة تحميل قائمة الشهادات
   - إظهار الشهادة الجديدة
8. ✅ معالجة الأخطاء:
   - المستخدم غير مؤهل: إظهار رسالة واضحة
   - الشهادة موجودة مسبقاً: إظهار تحذير
   - Validation errors: إظهار الأخطاء في الحقول

**UI Recommendation**:
```
┌─────────────────────────────────────┐
│  إصدار شهادة جديدة         [×]     │
├─────────────────────────────────────┤
│  👤 المستخدم: *                    │
│  [ابحث عن مستخدم...]               │
│                                     │
│  📚 الكورس: *                      │
│  [اختر الكورس ▼]                   │
│                                     │
│  📖 المستوى (اختياري):             │
│  [اختر المستوى ▼]                  │
│  ℹ️  اتركه فارغاً لشهادة الكورس    │
│                                     │
│  ℹ️  ملاحظات:                      │
│  - سيتم التحقق من أهلية المستخدم  │
│  - يجب أن يكون الكورس/المستوى      │
│    مكتملاً                         │
│                                     │
│  [❌ إلغاء]  [✓ إصدار]            │
└─────────────────────────────────────┘
```

**Workflow**:
1. المسؤول يفتح Modal "إصدار شهادة"
2. يختار المستخدم (AutoComplete)
3. يختار الكورس (Dropdown)
4. يختار المستوى (اختياري - Dropdown)
5. يضغط "إصدار"
6. Frontend يتحقق من البيانات
7. يرسل Request
8. Backend يتحقق من الأهلية:
   - إذا كان level_id = null: يتحقق من إكمال الكورس
   - إذا كان level_id محدد: يتحقق من إكمال المستوى و has_certificate = true
9. Backend يصدر الشهادة (يولد الصورة والQR Code)
10. يعيد Response بالشهادة الجديدة
11. Frontend يعرض النجاح ويحدث القائمة

---

### 4. إلغاء شهادة

**Endpoint**: `POST /api/v1/admin/certificates/{id}/revoke`

**المصادقة**: مطلوبة (Admin + `certificate.revoke`)

**الوصف**: إلغاء/حذف شهادة من النظام (Soft Delete).

**Path Parameters**:
- `id`: number (required) - معرف الشهادة

**Request Body**:
```json
{
  "reason": "سبب الإلغاء (اختياري)"
}
```

**Request Example**:
```http
POST /api/v1/admin/certificates/1/revoke
Authorization: Bearer {admin_token}
X-Context: dashboard
Content-Type: application/json

{
  "reason": "إصدار خطأ - تم إعادة الإصدار"
}
```

**Response Success (200)**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "deleted_at": "2026-01-10T14:00:00.000000Z"
  },
  "message": "messages.certificate.revoked",
  "status": 200
}
```

**Response Error (404 Not Found)**:
```json
{
  "success": false,
  "message": "messages.certificate.not_found",
  "status": 404
}
```

**Response Error (403 Forbidden)**:
```json
{
  "success": false,
  "message": "messages.permission.error",
  "status": 403
}
```

**ما يجب أن يفعله Frontend**:
1. ✅ إضافة زر "إلغاء" لكل شهادة في القائمة
2. ✅ عند الضغط، إظهار Confirmation Dialog
3. ✅ إضافة حقل (اختياري) لسبب الإلغاء
4. ✅ إظهار Loading أثناء الإلغاء
5. ✅ عند النجاح:
   - إزالة الشهادة من القائمة أو وضع علامة "ملغاة"
   - إظهار رسالة نجاح
6. ✅ إضافة Undo functionality (اختياري)

**UI Recommendation**:
```
┌─────────────────────────────────────┐
│  ⚠️  تأكيد الإلغاء          [×]     │
├─────────────────────────────────────┤
│  هل أنت متأكد من إلغاء هذه         │
│  الشهادة؟                           │
│                                     │
│  📚 الكورس: دورة البرمجة المتقدمة │
│  👤 المستخدم: أحمد محمد            │
│  📅 تاريخ الإصدار: 10/1/2026      │
│                                     │
│  سبب الإلغاء (اختياري):            │
│  [                                  ]│
│                                     │
│  [❌ إلغاء]  [✓ تأكيد]            │
└─────────────────────────────────────┘
```

**ملاحظة**: الشهادة الملغاة (Soft Delete) لن تظهر في القائمة العادية، ولكن يمكن للمسؤول عرضها من خلال Filters خاصة (إذا تم تطبيقها).

---

## معالجة الأخطاء

### رموز الحالة الشائعة:

| الكود | المعنى | المعالجة |
|------|--------|----------|
| 200 | نجاح | معالجة البيانات بشكل طبيعي |
| 201 | تم الإنشاء | إظهار رسالة نجاح وتحديث القائمة |
| 400 | Bad Request | عرض رسالة الخطأ من API |
| 401 | Unauthorized | إعادة توجيه لصفحة تسجيل الدخول |
| 403 | Forbidden | إظهار "ليس لديك صلاحية" |
| 404 | Not Found | إظهار "الشهادة غير موجودة" |
| 422 | Validation Error | عرض أخطاء Validation في الحقول |
| 500 | Server Error | إظهار رسالة خطأ عامة |

### رسائل الخطأ الشائعة:

#### عند إصدار شهادة:
- **"المستخدم غير مؤهل للحصول على شهادة الكورس"**: المستخدم لم يكمل الكورس أو جميع المستويات
- **"تم إصدار شهادة سابقة لهذا الكورس"**: الشهادة موجودة مسبقاً
- **"المستوى لا ينتمي لهذا الكورس"**: level_id محدد لا ينتمي للكورس
- **"هذا المستوى لا يحتوي على شهادة"**: المستوى المحدد has_certificate = false

---

## توصيات UI/UX

### 1. شاشة قائمة الشهادات:
- ✅ استخدم Table Design لعرض البيانات بشكل منظم
- ✅ أضف Filters متقدمة (فلترة متعددة)
- ✅ أضف Search Bar للبحث السريع
- ✅ أضف Sorting لكل عمود
- ✅ استخدم Pagination مع إظهار Total
- ✅ أضف Bulk Actions (اختياري): حذف متعدد، تصدير
- ✅ أضف Export to Excel/CSV
- ✅ أضف Refresh Button
- ✅ أضف Loading Skeleton أثناء التحميل
- ✅ استخدم Badges لتوضيح نوع الشهادة
- ✅ أضف Status Badge (نشط، ملغى)

### 2. Modal إصدار الشهادة:
- ✅ استخدم AutoComplete للمستخدمين (بحث سريع)
- ✅ استخدم Dropdowns للكورسات والمستويات
- ✅ أضف معلومات مساعدة (Tooltips) لكل حقل
- ✅ أضف Validation في الوقت الفعلي (Real-time)
- ✅ أضف Preview للمستخدم والكورس/المستوى المختار
- ✅ أضف Loading state أثناء الإصدار
- ✅ أضف Success animation عند النجاح

### 3. Modal تفاصيل الشهادة:
- ✅ عرض جميع المعلومات بشكل منظم
- ✅ عرض صورة الشهادة مع Zoom functionality
- ✅ عرض QR Code بوضوح
- ✅ إضافة Copy to Clipboard للأكواد والروابط
- ✅ إضافة Share functionality
- ✅ تصميم احترافي ومريح للعين

### 4. Dialogs و Modals:
- ✅ استخدم Confirmation Dialogs للإجراءات الحرجة (حذف، إلغاء)
- ✅ أضف Loading indicators
- ✅ أضف Error handling مع رسائل واضحة
- ✅ أضف Success feedback

### 5. الإحصائيات (اختياري):
- ✅ عرض Dashboard مع إحصائيات:
  - إجمالي الشهادات
  - الشهادات الصادرة هذا الشهر
  - الشهادات حسب الكورس
  - الشهادات حسب المستوى
- ✅ استخدام Charts (Bar, Pie) لعرض البيانات

---

## أمثلة كود

### React Example (Dashboard):

```javascript
// Services/CertificateService.js
import axios from 'axios';

const API_BASE_URL = 'https://api.example.com/api/v1';

export const CertificateService = {
  // جلب قائمة جميع الشهادات
  async getCertificates(params = {}) {
    const response = await axios.get(`${API_BASE_URL}/admin/certificates`, {
      params,
      headers: {
        'Authorization': `Bearer ${adminToken}`,
        'X-Context': 'dashboard'
      }
    });
    return response.data;
  },

  // جلب تفاصيل شهادة
  async getCertificate(id) {
    const response = await axios.get(`${API_BASE_URL}/admin/certificates/${id}`, {
      headers: {
        'Authorization': `Bearer ${adminToken}`,
        'X-Context': 'dashboard'
      }
    });
    return response.data;
  },

  // إصدار شهادة
  async issueCertificate(userId, courseId, levelId = null) {
    const response = await axios.post(
      `${API_BASE_URL}/admin/certificates/issue`,
      {
        user_id: userId,
        course_id: courseId,
        level_id: levelId
      },
      {
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'X-Context': 'dashboard',
          'Content-Type': 'application/json'
        }
      }
    );
    return response.data;
  },

  // إلغاء شهادة
  async revokeCertificate(id, reason = null) {
    const response = await axios.post(
      `${API_BASE_URL}/admin/certificates/${id}/revoke`,
      { reason },
      {
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'X-Context': 'dashboard',
          'Content-Type': 'application/json'
        }
      }
    );
    return response.data;
  }
};
```

### React Component Example:

```jsx
// Components/CertificateIssueModal.jsx
import React, { useState } from 'react';
import { Modal, Form, Select, Button, Alert, Spin } from 'antd';
import { CertificateService } from '../Services/CertificateService';

const CertificateIssueModal = ({ visible, onClose, onSuccess }) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (values) => {
    setLoading(true);
    setError(null);

    try {
      const result = await CertificateService.issueCertificate(
        values.user_id,
        values.course_id,
        values.level_id || null
      );

      if (result.success) {
        form.resetFields();
        onSuccess(result.data);
        onClose();
      }
    } catch (err) {
      setError(err.response?.data?.message || 'حدث خطأ أثناء إصدار الشهادة');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      title="إصدار شهادة جديدة"
      visible={visible}
      onCancel={onClose}
      footer={null}
      width={600}
    >
      <Form
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
      >
        {error && (
          <Alert
            message={error}
            type="error"
            showIcon
            closable
            onClose={() => setError(null)}
            style={{ marginBottom: 16 }}
          />
        )}

        <Form.Item
          name="user_id"
          label="المستخدم"
          rules={[{ required: true, message: 'يرجى اختيار المستخدم' }]}
        >
          <Select
            showSearch
            placeholder="ابحث عن مستخدم..."
            optionFilterProp="children"
            filterOption={(input, option) =>
              option.children.toLowerCase().indexOf(input.toLowerCase()) >= 0
            }
          >
            {/* Load users from API */}
          </Select>
        </Form.Item>

        <Form.Item
          name="course_id"
          label="الكورس"
          rules={[{ required: true, message: 'يرجى اختيار الكورس' }]}
        >
          <Select placeholder="اختر الكورس">
            {/* Load courses from API */}
          </Select>
        </Form.Item>

        <Form.Item
          name="level_id"
          label="المستوى (اختياري)"
          help="اتركه فارغاً لإصدار شهادة الكورس"
        >
          <Select placeholder="اختر المستوى (اختياري)" allowClear>
            {/* Load levels from API based on course_id */}
          </Select>
        </Form.Item>

        <Form.Item>
          <Button type="default" onClick={onClose} style={{ marginRight: 8 }}>
            إلغاء
          </Button>
          <Button type="primary" htmlType="submit" loading={loading}>
            إصدار الشهادة
          </Button>
        </Form.Item>
      </Form>
    </Modal>
  );
};

export default CertificateIssueModal;
```

---

## ملاحظات مهمة

1. **الأهلية للإصدار**: عند إصدار شهادة يدوياً، يجب أن يكون المستخدم مؤهلاً:
   - للكورس: يجب أن يكون الكورس مكتملاً وجميع المستويات مكتملة
   - للمستوى: يجب أن يكون المستوى مكتملاً و has_certificate = true

2. **التكرار**: لا يمكن إصدار شهادة مكررة لنفس المستخدم في نفس الكورس/المستوى

3. **الصور**: جميع روابط الصور تعود من `MediaUrlService::toUrl()` - تأكد من معالجة الروابط بشكل صحيح

4. **Pagination**: استخدم `meta` object للتحكم في Pagination

5. **Permissions**: تأكد من التحقق من الصلاحيات قبل عرض الأزرار/الإجراءات

6. **Validation**: Backend يتحقق من جميع البيانات - Frontend يمكن أن يضيف Validation إضافي لتجربة مستخدم أفضل

---

## الأسئلة الشائعة (FAQ)

**Q: هل يمكن إصدار شهادة لمستخدم لم يكمل الكورس؟**
A: لا، Backend يتحقق من الأهلية قبل الإصدار

**Q: ماذا يحدث عند إلغاء شهادة؟**
A: يتم Soft Delete - الشهادة لا تظهر في القوائم العادية، ولكن البيانات تبقى في قاعدة البيانات

**Q: هل يمكن إعادة تفعيل شهادة ملغاة؟**
A: حالياً لا - يمكن إضافة هذه الميزة لاحقاً

**Q: كيف يمكن تصدير قائمة الشهادات؟**
A: يمكن إضافة Endpoint جديد للتصدير أو استخدام البيانات الحالية لتصديرها من Frontend

---

## الدعم

للأسئلة التقنية أو المشاكل، يرجى التواصل مع فريق التطوير.
