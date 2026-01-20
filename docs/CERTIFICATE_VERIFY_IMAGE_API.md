# Certificate Verify Image API - التحقق من صورة الشهادة

## نظرة عامة

هذا الـ API يتيح التحقق من وجود صورة الشهادة وتوليدها تلقائياً إذا كانت مفقودة أو محذوفة.

## Endpoint

```
GET /api/v1/user/certificates/{id}/verify-image
```

## المصادقة

يتطلب:
- Bearer Token (User or Admin)

## URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | **نعم** | معرّف الشهادة |

## Request Example

```bash
GET /api/v1/user/certificates/42/verify-image
```

## Response Format

### Success Response

**Status Code:** `200 OK`

```json
{
  "success": true,
  "data": {
    "id": 42,
    "certificate_code": "CERT-3-2-6",
    "user_id": 10,
    "course_id": 5,
    "level_id": null,
    "issued_at": "2024-01-15T10:30:00.000000Z",
    "status": "active",
    "image_url": "certificates/CERT-3-2-6.png",
    "qr_code": "certificates/qr_codes/CERT-3-2-6.png",
    "user": {
      "id": 10,
      "name": "أحمد محمد",
      "email": "ahmed@example.com"
    },
    "course": {
      "id": 5,
      "title": "دبلوماسية التفاوض",
      "description": "..."
    },
    "level": null,
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

## Error Responses

### 1. الشهادة غير موجودة

**Status Code:** `404 Not Found`

```json
{
  "status": false,
  "message": "الشهادة غير موجودة"
}
```

**متى يحدث:**
- `certificate_id` غير موجود في قاعدة البيانات
- الشهادة ملغاة (status != 'active')

---

### 2. غير مصرح

**Status Code:** `401 Unauthorized`

```json
{
  "status": false,
  "message": "Unauthenticated."
}
```

**متى يحدث:**
- Bearer Token غير موجود أو منتهي الصلاحية

---

### 3. ممنوع من الوصول

**Status Code:** `403 Forbidden`

```json
{
  "status": false,
  "message": "غير مصرح لك بعرض هذه الشهادة"
}
```

**متى يحدث:**
- المستخدم يحاول الوصول لشهادة لا تخصه (إلا إذا كان Admin)

---

### 4. خطأ في معالجة الشهادة

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

## Flow Diagram - مخطط سير العمل

```
START
  ↓
التحقق من المصادقة
  ↓
البحث عن الشهادة (certificate_id)
  ↓
هل الشهادة موجودة؟
  ↓
NO → إرجاع 404 (الشهادة غير موجودة)
  ↓
YES → التحقق من الصلاحيات
  ↓
هل لديه صلاحية؟
  ↓
NO → إرجاع 403 (ممنوع من الوصول)
  ↓
YES → هل صورة PNG موجودة؟
  ↓
NO → توليد الصورة من PDF
  ↓
هل تم التوليد بنجاح؟
  ↓
YES → حفظ مسار الصورة في قاعدة البيانات
  ↓
NO → تسجيل الخطأ والمتابعة
  ↓
إرجاع الشهادة (200)
  ↓
END
```

---

## Business Logic - المنطق البرمجي

### 1. التحقق من وجود الشهادة
```php
$certificate = Certificate::where('id', $certificateId)
    ->where('status', 'active')
    ->with(['user', 'course', 'level'])
    ->first();

if (!$certificate) {
    MessageService::abort(404, 'messages.certificate.not_found');
}
```

### 2. التحقق من الصورة
```php
$shouldGenerateImage = false;

if (!$certificate->image_url) {
    $shouldGenerateImage = true;
} else {
    $imagePath = storage_path('app/public/' . $certificate->image_url);
    if (!file_exists($imagePath)) {
        $shouldGenerateImage = true;
    }
}
```

### 3. توليد الصورة
```php
if ($shouldGenerateImage) {
    try {
        $imagePath = $this->generateCertificateImageFromPdf($certificate);
        if ($imagePath) {
            $certificate->image_url = $imagePath;
            $certificate->save();
            $certificate->refresh();
        }
    } catch (\Exception $e) {
        Log::warning("Failed to generate certificate image");
        // المتابعة بدون صورة
    }
}
```

---

## Usage Examples - أمثلة الاستخدام

### JavaScript (Fetch)

```javascript
const response = await fetch('https://api.diplomasi.com/api/v1/user/certificates/42/verify-image', {
  method: 'GET',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN',
    'Accept': 'application/json',
  }
});

const data = await response.json();

if (data.success) {
  console.log('Certificate:', data.data);
  console.log('Image URL:', data.data.image_url);
} else {
  console.error('Error:', data.message);
}
```

### cURL

```bash
curl -X GET \
  'https://api.diplomasi.com/api/v1/user/certificates/42/verify-image' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Accept: application/json'
```

### Flutter (Dart)

```dart
final response = await http.get(
  Uri.parse('https://api.diplomasi.com/api/v1/user/certificates/42/verify-image'),
  headers: {
    'Authorization': 'Bearer $token',
    'Accept': 'application/json',
  },
);

if (response.statusCode == 200) {
  final data = jsonDecode(response.body);
  print('Certificate: ${data['data']}');
  print('Image URL: ${data['data']['image_url']}');
} else {
  print('Error: ${response.body}');
}
```

---

## متى تستخدم هذا الـ API؟

### Use Cases - حالات الاستخدام:

1. **عرض الشهادة في التطبيق**
   - قبل عرض الشهادة للمستخدم، تأكد من وجود الصورة

2. **إصلاح الصور المفقودة**
   - إذا تم حذف الصور من الخادم عن طريق الخطأ، هذا الـ API يولدها تلقائياً

3. **تحديث الشهادات القديمة**
   - إذا تم تحديث تصميم الشهادة، يمكن إعادة توليد الصور

4. **التحقق من جاهزية الشهادة**
   - قبل مشاركة الشهادة أو تحميلها، تأكد من جاهزيتها

---

## Notes - ملاحظات

1. **الصورة تُولد من PDF**
   - الصورة النهائية تُولد من PDF باستخدام Ghostscript أو Imagick
   - النص العربي يظهر متصلاً واحترافياً

2. **الصلاحيات**
   - المستخدم يمكنه الوصول فقط لشهاداته
   - الـ Admin يمكنه الوصول لجميع الشهادات

3. **الأداء**
   - إذا كانت الصورة موجودة، الـ API سريع جداً
   - إذا احتاجت توليد، قد يستغرق 1-3 ثواني

4. **Error Handling**
   - إذا فشل توليد الصورة، الـ API يُرجع الشهادة بدون صورة
   - لا يفشل الطلب بالكامل

---

## Related Endpoints

- `GET /api/v1/user/certificates` - قائمة الشهادات
- `GET /api/v1/user/certificates/{id}` - عرض شهادة محددة
- `GET /api/v1/user/certificates/{id}/download` - تحميل الشهادة
- `GET /certificates/{code}/verify` - التحقق من الشهادة (Web)
