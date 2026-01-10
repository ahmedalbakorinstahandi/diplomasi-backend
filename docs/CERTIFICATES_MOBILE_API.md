# دليل API الشهادات للموبايل

هذا الدليل يشرح بالتفصيل كيفية استخدام APIs الخاصة بالشهادات في تطبيق الموبايل.

## جدول المحتويات
- [المقدمة](#المقدمة)
- [المصادقة (Authentication)](#المصادقة-authentication)
- [APIs المتاحة](#apis-المتاحة)
  - [1. قائمة شهادات المستخدم](#1-قائمة-شهادات-المستخدم)
  - [2. تفاصيل شهادة محددة](#2-تفاصيل-شهادة-محددة)
  - [3. تحميل صورة الشهادة](#3-تحميل-صورة-الشهادة)
  - [4. التحقق من صحة الشهادة (Public)](#4-التحقق-من-صحة-الشهادة-public)
- [معالجة الأخطاء](#معالجة-الأخطاء)
- [توصيات UI/UX](#توصيات-uiux)
- [أمثلة كود](#أمثلة-كود)

---

## المقدمة

نظام الشهادات يسمح للمستخدمين بالحصول على شهادات عند إكمال كورس كامل أو عند إكمال مستوى محدد يحتوي على شهادة (`has_certificate = true`).

الشهادات يتم إصدارها تلقائياً عند:
- إكمال جميع المستويات في الكورس
- إكمال مستوى محدد يحتوي على `has_certificate = true`

### أنواع الشهادات:
1. **شهادة الكورس**: عند إكمال الكورس بالكامل (level_id = null)
2. **شهادة المستوى**: عند إكمال مستوى محدد يحتوي على شهادة (level_id محدد)

---

## المصادقة (Authentication)

جميع APIs التالية (ما عدا التحقق) تتطلب:
- **Header**: `Authorization: Bearer {token}`
- **Context Header**: `X-Context: app` (للتطبيق)

### مثال:
```http
GET /api/v1/user/certificates
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
X-Context: app
```

---

## APIs المتاحة

### 1. قائمة شهادات المستخدم

**Endpoint**: `GET /api/v1/user/certificates`

**المصادقة**: مطلوبة (User)

**الوصف**: جلب قائمة بجميع شهادات المستخدم المسجل حالياً.

**Query Parameters** (اختيارية):
```
per_page: number (default: 20) - عدد النتائج في الصفحة
page: number (default: 1) - رقم الصفحة
sort_field: string (default: 'issued_at') - حقل الترتيب (issued_at, created_at, id)
sort_order: string (default: 'desc') - اتجاه الترتيب (asc, desc)
course_id: number (optional) - فلترة حسب الكورس
level_id: number (optional) - فلترة حسب المستوى
search: string (optional) - بحث في certificate_code
```

**Request Example**:
```http
GET /api/v1/user/certificates?per_page=10&page=1&sort_field=issued_at&sort_order=desc
Authorization: Bearer {token}
X-Context: app
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
        "qr_code": "https://example.com/storage/certificates/qr/CERT-20260110132845-5-3-10-XYZ789.png",
        "image_url": "https://example.com/storage/certificates/CERT-20260110132845-5-3-10-XYZ789.png",
        "verification_url": "https://example.com/api/v1/general/certificates/verify/CERT-20260110132845-5-3-10-XYZ789",
        "download_url": "https://example.com/api/v1/user/certificates/2/download",
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
    "per_page": 10,
    "total": 2,
    "last_page": 1,
    "from": 1,
    "to": 2
  },
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 2,
    "last_page": 1
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

**ما يجب أن يفعله Frontend**:
1. ✅ عرض قائمة الشهادات في شاشة "شهاداتي" أو "My Certificates"
2. ✅ إظهار اسم الكورس/المستوى وتاريخ الإصدار
3. ✅ تمييز نوع الشهادة (كورس أم مستوى)
4. ✅ إضافة زر "تحميل" لكل شهادة
5. ✅ إضافة زر "مشاركة" للمشاركة عبر QR Code
6. ✅ دعم Pagination إذا كانت النتائج كثيرة
7. ✅ إضافة Pull to Refresh
8. ✅ إضافة Filter حسب الكورس إذا كان لديك عدة كورسات

**UI Recommendation**:
```
┌─────────────────────────────┐
│  شهاداتي                    │
├─────────────────────────────┤
│  🔖 دورة البرمجة المتقدمة  │
│     تاريخ: 10 يناير 2026   │
│     [تحميل] [مشاركة]       │
├─────────────────────────────┤
│  🔖 المستوى الأول           │
│     دورة البرمجة المتقدمة  │
│     تاريخ: 10 يناير 2026   │
│     [تحميل] [مشاركة]       │
└─────────────────────────────┘
```

---

### 2. تفاصيل شهادة محددة

**Endpoint**: `GET /api/v1/user/certificates/{id}`

**المصادقة**: مطلوبة (User - يجب أن تكون الشهادة للمستخدم الحالي)

**الوصف**: جلب تفاصيل شهادة محددة للمستخدم الحالي.

**Path Parameters**:
- `id`: number (required) - معرف الشهادة

**Request Example**:
```http
GET /api/v1/user/certificates/1
Authorization: Bearer {token}
X-Context: app
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
    "image_url": "https://example.com/storage/certificates/CERT-20260110132840-5-3-0-ABC123.png",
    "verification_url": "https://example.com/api/v1/general/certificates/verify/CERT-20260110132840-5-3-0-ABC123",
    "download_url": "https://example.com/api/v1/user/certificates/1/download",
    "course": {
      "id": 3,
      "title": "دورة البرمجة المتقدمة",
      "description": "دورة شاملة لتعلم البرمجة...",
      "image_url": "..."
    },
    "level": null
  },
  "status": 200
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

**Response Error (404 Not Found)**:
```json
{
  "success": false,
  "message": "messages.certificate.not_found",
  "status": 404
}
```

**ما يجب أن يفعله Frontend**:
1. ✅ عرض تفاصيل الشهادة في شاشة منفصلة
2. ✅ إظهار صورة الشهادة (image_url) بشكل كامل
3. ✅ إظهار معلومات الكورس/المستوى
4. ✅ إظهار QR Code للمشاركة والتحقق
5. ✅ إضافة أزرار: تحميل، مشاركة، طباعة
6. ✅ إظهار كود الشهادة للتحقق

**UI Recommendation**:
```
┌─────────────────────────────┐
│  [←] تفاصيل الشهادة        │
├─────────────────────────────┤
│                             │
│    [صورة الشهادة الكاملة]  │
│                             │
├─────────────────────────────┤
│  📚 دورة البرمجة المتقدمة  │
│  📅 تاريخ الإصدار: 10/1/26 │
│  🏷️  الكود: CERT-2026...   │
│                             │
│  [QR Code للمشاركة]        │
│                             │
│  [⬇️ تحميل] [📤 مشاركة]   │
│  [🖨️ طباعة] [✓ التحقق]    │
└─────────────────────────────┘
```

---

### 3. تحميل صورة الشهادة

**Endpoint**: `GET /api/v1/user/certificates/{id}/download`

**المصادقة**: مطلوبة (User - يجب أن تكون الشهادة للمستخدم الحالي)

**الوصف**: تحميل صورة الشهادة كملف PNG.

**Path Parameters**:
- `id`: number (required) - معرف الشهادة

**Request Example**:
```http
GET /api/v1/user/certificates/1/download
Authorization: Bearer {token}
X-Context: app
```

**Response Success (200)**:
```
Content-Type: image/png
Content-Disposition: attachment; filename="CERT-20260110132840-5-3-0-ABC123.png"

[Binary PNG Image Data]
```

**Response Error (404 Not Found)**:
```json
{
  "success": false,
  "message": "صورة الشهادة غير موجودة",
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
1. ✅ عند الضغط على زر "تحميل"، استدعاء هذا API
2. ✅ حفظ الصورة في مجلد Downloads على الجهاز
3. ✅ إظهار رسالة نجاح "تم التحميل بنجاح"
4. ✅ السماح للمستخدم بفتح الصورة مباشرة
5. ✅ على iOS: استخدام Share Sheet للمشاركة
6. ✅ على Android: استخدام DownloadManager

**UI Recommendation**:
- عند الضغط على "تحميل":
  1. إظهار Loading indicator
  2. تحميل الملف
  3. حفظه في Downloads
  4. إظهار Toast/Snackbar: "✅ تم التحميل بنجاح"
  5. إعطاء خيار "فتح" أو "مشاركة"

---

### 4. التحقق من صحة الشهادة (Public)

**Endpoint**: `GET /api/v1/general/certificates/verify/{certificateCode}`

**المصادقة**: غير مطلوبة (Public API)

**الوصف**: التحقق من صحة شهادة عبر كود الشهادة (مفيد للمشاركة العامة أو QR Code).

**Path Parameters**:
- `certificateCode`: string (required) - كود الشهادة (مثل: CERT-20260110132840-5-3-0-ABC123)

**Request Example**:
```http
GET /api/v1/general/certificates/verify/CERT-20260110132840-5-3-0-ABC123
```

**Response Success (200) - شهادة صحيحة**:
```json
{
  "success": true,
  "data": {
    "valid": true,
    "certificate": {
      "id": 1,
      "certificate_code": "CERT-20260110132840-5-3-0-ABC123",
      "issued_at": "2026-01-10T13:28:40.000000Z"
    },
    "user_name": "أحمد محمد",
    "course_title": "دورة البرمجة المتقدمة",
    "level_title": null,
    "issued_at": "2026-01-10"
  },
  "status": 200
}
```

**Response Error (404) - شهادة غير موجودة**:
```json
{
  "success": false,
  "data": {
    "valid": false,
    "message": "الشهادة غير موجودة"
  },
  "status": 404
}
```

**ما يجب أن يفعله Frontend**:
1. ✅ إظهار شاشة "التحقق من الشهادة" يمكن الوصول إليها بدون تسجيل دخول
2. ✅ إدخال كود الشهادة أو مسح QR Code
3. ✅ عند مسح QR Code، استخراج الكود من URL واستدعاء API
4. ✅ إظهار تفاصيل الشهادة إذا كانت صحيحة:
   - اسم المستخدم
   - اسم الكورس/المستوى
   - تاريخ الإصدار
5. ✅ إظهار رسالة خطأ إذا كانت الشهادة غير صحيحة
6. ✅ تصميم جميل ومهني لشاشة التحقق (مهم للثقة)

**UI Recommendation**:
```
┌─────────────────────────────┐
│  التحقق من الشهادة          │
├─────────────────────────────┤
│                             │
│  📷 [مسح QR Code]           │
│                             │
│  أو                          │
│                             │
│  [إدخال كود الشهادة]       │
│  [                    ]     │
│                             │
│  [✓ التحقق]                 │
└─────────────────────────────┘

عند التحقق الناجح:
┌─────────────────────────────┐
│  ✓ شهادة صحيحة              │
├─────────────────────────────┤
│  👤 المستخدم: أحمد محمد    │
│  📚 الكورس: دورة البرمجة   │
│  📅 تاريخ: 10 يناير 2026   │
│                             │
│  [إغلاق]                    │
└─────────────────────────────┘
```

---

## معالجة الأخطاء

### رموز الحالة الشائعة:

| الكود | المعنى | المعالجة |
|------|--------|----------|
| 200 | نجاح | معالجة البيانات بشكل طبيعي |
| 201 | تم الإنشاء | إظهار رسالة نجاح |
| 400 | Bad Request | التحقق من البيانات المرسلة |
| 401 | Unauthorized | إعادة توجيه لصفحة تسجيل الدخول |
| 403 | Forbidden | إظهار رسالة "ليس لديك صلاحية" |
| 404 | Not Found | إظهار "الشهادة غير موجودة" |
| 500 | Server Error | إظهار رسالة خطأ عامة |

### مثال على معالجة الأخطاء في React Native:

```javascript
try {
  const response = await fetch('https://api.example.com/api/v1/user/certificates', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'X-Context': 'app',
      'Content-Type': 'application/json'
    }
  });

  const data = await response.json();

  if (!response.ok) {
    switch (response.status) {
      case 401:
        // إعادة توجيه لصفحة تسجيل الدخول
        navigation.navigate('Login');
        break;
      case 403:
        Alert.alert('خطأ', 'ليس لديك صلاحية لعرض هذه الشهادة');
        break;
      case 404:
        Alert.alert('غير موجود', 'الشهادة غير موجودة');
        break;
      default:
        Alert.alert('خطأ', data.message || 'حدث خطأ غير متوقع');
    }
    return;
  }

  // معالجة البيانات الناجحة
  setCertificates(data.data.data);
} catch (error) {
  Alert.alert('خطأ', 'حدث خطأ في الاتصال. تحقق من اتصال الإنترنت');
}
```

---

## توصيات UI/UX

### 1. شاشة قائمة الشهادات:
- ✅ استخدم Card Design لعرض كل شهادة
- ✅ أضف Badge لتوضيح نوع الشهادة (كورس / مستوى)
- ✅ أضف History/Empty State إذا لم يكن لدى المستخدم شهادات
- ✅ استخدم Pull to Refresh
- ✅ أضف Loading Skeleton أثناء التحميل
- ✅ استخدم Infinite Scroll أو Pagination

### 2. شاشة تفاصيل الشهادة:
- ✅ عرض الصورة بشكل كامل مع Zoom/Pan
- ✅ إضافة Share Sheet للمشاركة
- ✅ إضافة Print functionality (خاصة على iOS)
- ✅ عرض QR Code بشكل واضح للمشاركة
- ✅ إضافة Copy to Clipboard لكود الشهادة

### 3. شاشة التحقق من الشهادة:
- ✅ تصميم احترافي يعطي الثقة
- ✅ دعم مسح QR Code مباشرة
- ✅ إظهار النتيجة بشكل واضح (صحيحة/غير صحيحة)
- ✅ إضافة Logo الخاص بالمنصة

### 4. الإشعارات:
- ✅ عند إصدار شهادة جديدة، إظهار Push Notification
- ✅ إظهار Badge على أيقونة "الشهادات" عند وجود شهادة جديدة
- ✅ إضافة إشعار محلي (Local Notification) عند إكمال الكورس/المستوى

---

## أمثلة كود

### React Native Example:

```javascript
// Service/CertificateService.js
import axios from 'axios';

const API_BASE_URL = 'https://api.example.com/api/v1';

export const CertificateService = {
  // جلب قائمة الشهادات
  async getCertificates(params = {}) {
    const response = await axios.get(`${API_BASE_URL}/user/certificates`, {
      params,
      headers: {
        'Authorization': `Bearer ${token}`,
        'X-Context': 'app'
      }
    });
    return response.data;
  },

  // جلب تفاصيل شهادة
  async getCertificate(id) {
    const response = await axios.get(`${API_BASE_URL}/user/certificates/${id}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'X-Context': 'app'
      }
    });
    return response.data;
  },

  // تحميل الشهادة
  async downloadCertificate(id) {
    const response = await axios.get(`${API_BASE_URL}/user/certificates/${id}/download`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'X-Context': 'app'
      },
      responseType: 'blob'
    });
    return response.data;
  },

  // التحقق من الشهادة
  async verifyCertificate(certificateCode) {
    const response = await axios.get(`${API_BASE_URL}/general/certificates/verify/${certificateCode}`);
    return response.data;
  }
};
```

### React Native Component Example:

```javascript
// Components/CertificateCard.js
import React from 'react';
import { View, Text, Image, TouchableOpacity, StyleSheet } from 'react-native';

const CertificateCard = ({ certificate, onPress, onDownload }) => {
  const isCourseCertificate = certificate.level_id === null;
  
  return (
    <TouchableOpacity style={styles.card} onPress={() => onPress(certificate.id)}>
      <View style={styles.header}>
        <Text style={styles.title}>
          {isCourseCertificate 
            ? certificate.course.title 
            : certificate.level.title}
        </Text>
        <View style={styles.badge}>
          <Text style={styles.badgeText}>
            {isCourseCertificate ? 'كورس' : 'مستوى'}
          </Text>
        </View>
      </View>
      
      <Text style={styles.date}>
        تاريخ الإصدار: {new Date(certificate.issued_at).toLocaleDateString('ar')}
      </Text>
      
      <View style={styles.actions}>
        <TouchableOpacity 
          style={styles.button} 
          onPress={() => onDownload(certificate.id)}
        >
          <Text style={styles.buttonText}>⬇️ تحميل</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.button}>
          <Text style={styles.buttonText}>📤 مشاركة</Text>
        </TouchableOpacity>
      </View>
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8
  },
  title: {
    fontSize: 18,
    fontWeight: 'bold',
    flex: 1
  },
  badge: {
    backgroundColor: '#007AFF',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4
  },
  badgeText: {
    color: '#fff',
    fontSize: 12
  },
  date: {
    color: '#666',
    marginBottom: 12
  },
  actions: {
    flexDirection: 'row',
    gap: 8
  },
  button: {
    flex: 1,
    backgroundColor: '#f0f0f0',
    padding: 10,
    borderRadius: 8,
    alignItems: 'center'
  },
  buttonText: {
    fontSize: 14
  }
});

export default CertificateCard;
```

---

## ملاحظات مهمة

1. **الصور**: جميع روابط الصور (`image_url`, `qr_code`) تعود من `MediaUrlService::toUrl()` - تأكد من معالجة الروابط بشكل صحيح
2. **التوقيتات**: جميع التواريخ تأتي بصيغة ISO 8601 - استخدم `Date` object لمعالجتها
3. **Pagination**: استخدم `meta` object للتحكم في Pagination
4. **Loading States**: أضف Loading indicators أثناء جلب البيانات
5. **Offline Support**: فكر في إضافة Cache للشهادات لعرضها offline
6. **Error Handling**: عالج جميع الأخطاء بشكل مناسب وأظهر رسائل واضحة للمستخدم

---

## الأسئلة الشائعة (FAQ)

**Q: ماذا يحدث إذا لم يكن لدى المستخدم شهادات؟**
A: API يعيد `data: []` - أضف Empty State في UI

**Q: هل يمكن للمستخدم إصدار شهادة يدوياً؟**
A: لا، الشهادات تصدر تلقائياً عند إكمال الكورس/المستوى. فقط المسؤولون يمكنهم إصدارها يدوياً

**Q: كيف يمكن مشاركة الشهادة؟**
A: يمكن مشاركة `verification_url` أو `certificate_code` أو QR Code

**Q: هل يمكن تحميل الشهادة أكثر من مرة؟**
A: نعم، لا يوجد حد لعدد مرات التحميل

---

## الدعم

للأسئلة التقنية أو المشاكل، يرجى التواصل مع فريق التطوير.
