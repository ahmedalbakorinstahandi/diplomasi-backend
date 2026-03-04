# Reengagement Reminder API (تذكيرات العودة)

توثيق واجهات إدارة قواعد تذكيرات العودة من لوحة التحكم (Dashboard).

---

## الأساسيات

| البند | القيمة |
|--------|--------|
| Base URL | `{BASE}/api/v1/admin` |
| المصادقة | `Bearer` token (Sanctum) |
| السياق | يجب إرسال الهيدر `X-Context: dashboard` للطلبات الأدمن |
| Content-Type | `application/json` |

**الصلاحيات المطلوبة (حسب الإجراء):**

- عرض القائمة أو عنصر واحد: `reengagement_reminder.view`
- إنشاء: `reengagement_reminder.create`
- تحديث: `reengagement_reminder.update`
- حذف: `reengagement_reminder.delete`

---

## نموذج العنصر (Resource)

كل استجابة تحتوي على عنصر أو عناصر من النوع التالي:

```json
{
  "id": 1,
  "amount": 3,
  "unit": "day",
  "title": "اشتقنالك في دبلوماسي",
  "body": "ثلاث أيام غياب كفاية. ارجع الآن وكمل رحلتك من آخر نقطة وصلت لها.",
  "is_active": true,
  "sort_order": 1,
  "created_at": "2026-03-04T12:00:00.000000Z",
  "updated_at": "2026-03-04T12:00:00.000000Z"
}
```

| الحقل | النوع | الوصف |
|--------|--------|--------|
| `id` | integer | المعرّف |
| `amount` | integer | عدد الوحدات (مثلاً 3 أيام، 2 أسبوع) |
| `unit` | string | الوحدة: `day` \| `week` \| `month` \| `year` |
| `title` | string | عنوان الإشعار |
| `body` | string | نص الإشعار (يدعم placeholders: `{{first_name}}`, `{{amount}}`, `{{unit_label}}`) |
| `is_active` | boolean | تفعيل/إيقاف هذه القاعدة |
| `sort_order` | integer | ترتيب العرض (الأصغر أولاً) |
| `created_at` | string (ISO 8601) | تاريخ الإنشاء |
| `updated_at` | string (ISO 8601) | تاريخ آخر تحديث |

---

## 1. قائمة قواعد التذكير

**`GET /reengagement-reminders`**

يعيد قائمة القواعد مع إمكانية الفلترة والترتيب والترقيم.

### Query Parameters (اختيارية)

| المعامل | النوع | الوصف |
|---------|--------|--------|
| `per_page` | integer | عدد العناصر في الصفحة (افتراضي: 20) |
| `page` | integer | رقم الصفحة |
| `sort_field` | string | الحقل للترتيب: `sort_order`, `amount`, `unit`, `title`, `body`, `is_active`, `created_at`, `updated_at` |
| `sort_order` | string | `asc` أو `desc` (افتراضي للقائمة: `asc`) |
| `search` | string | بحث في `title` و `body` |
| `amount` | integer | فلتر حسب `amount` (تطابق) |
| `unit` | string | فلتر حسب `unit`: `day`, `week`, `month`, `year` |
| `is_active` | boolean | فلتر حسب الحالة: `0` أو `1` |
| `unit[]` | array | فلتر: عدة وحدات (مثلاً `unit[]=day&unit[]=week`) |
| `is_active[]` | array | فلتر: عدة قيم |
| `created_at_from` / `created_at_to` | string | نطاق تاريخ الإنشاء |
| `updated_at_from` / `updated_at_to` | string | نطاق تاريخ التحديث |

### مثال طلب

```http
GET /api/v1/admin/reengagement-reminders?per_page=10&sort_field=sort_order&sort_order=asc&unit=day
```

### مثال استجابة (200)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "amount": 1,
      "unit": "day",
      "title": "مكانك لسا محجوز عندنا",
      "body": "رجعتك اليوم بتعمل فرق كبير...",
      "is_active": true,
      "sort_order": 1,
      "created_at": "2026-03-04T12:00:00.000000Z",
      "updated_at": "2026-03-04T12:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 5,
    ...
  }
}
```

---

## 2. عرض قاعدة واحدة

**`GET /reengagement-reminders/{id}`**

يعيد قاعدة تذكير واحدة حسب المعرّف.

### مثال طلب

```http
GET /api/v1/admin/reengagement-reminders/1
```

### استجابة ناجحة (200)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "amount": 1,
    "unit": "day",
    "title": "مكانك لسا محجوز عندنا",
    "body": "رجعتك اليوم بتعمل فرق كبير. افتح دبلوماسي وخذ خطوة صغيرة ترفع مستواك بسرعة.",
    "is_active": true,
    "sort_order": 1,
    "created_at": "2026-03-04T12:00:00.000000Z",
    "updated_at": "2026-03-04T12:00:00.000000Z"
  }
}
```

### أخطاء محتملة

- **404** — قاعدة التذكير غير موجودة.

---

## 3. إنشاء قاعدة تذكير

**`POST /reengagement-reminders`**

إنشاء قاعدة تذكير جديدة.

### Body (JSON)

| الحقل | النوع | مطلوب | الوصف |
|--------|--------|--------|--------|
| `amount` | integer | نعم | عدد الوحدات (≥ 1) |
| `unit` | string | نعم | `day` \| `week` \| `month` \| `year` |
| `title` | string | نعم | عنوان الإشعار (حد أقصى 255 حرفاً) |
| `body` | string | نعم | نص الإشعار |
| `is_active` | boolean | لا | افتراضي: `true` |
| `sort_order` | integer | لا | افتراضي: `0`، يجب أن يكون ≥ 0 |

### مثال طلب

```http
POST /api/v1/admin/reengagement-reminders
Content-Type: application/json

{
  "amount": 3,
  "unit": "week",
  "title": "ثلاث أسابيع غياب",
  "body": "مرّ ثلاث أسابيع على آخر زيارة. نرجعك للمسار.",
  "is_active": true,
  "sort_order": 6
}
```

### استجابة ناجحة (201)

```json
{
  "success": true,
  "data": {
    "id": 6,
    "amount": 3,
    "unit": "week",
    "title": "ثلاث أسابيع غياب",
    "body": "مرّ ثلاث أسابيع على آخر زيارة. نرجعك للمسار.",
    "is_active": true,
    "sort_order": 6,
    "created_at": "2026-03-04T14:00:00.000000Z",
    "updated_at": "2026-03-04T14:00:00.000000Z"
  },
  "message": "messages.reengagement_reminder.created"
}
```

### أخطاء التحقق (422)

- `amount` غير مرفق أو ليس رقماً صحيحاً ≥ 1
- `unit` غير مرفق أو ليس أحد: `day`, `week`, `month`, `year`
- `title` مطلوب أو أطول من 255 حرفاً
- `body` مطلوب
- `sort_order` سالب

---

## 4. تحديث قاعدة تذكير

**`PUT /reengagement-reminders/{id}`**

تحديث قاعدة تذكير موجودة. جميع الحقول اختيارية (يُحدَّث فقط ما يُرسل).

### Body (JSON)

| الحقل | النوع | مطلوب | الوصف |
|--------|--------|--------|--------|
| `amount` | integer | لا | عدد الوحدات (≥ 1) |
| `unit` | string | لا | `day` \| `week` \| `month` \| `year` |
| `title` | string | لا | عنوان الإشعار (حد أقصى 255 حرفاً) |
| `body` | string | لا | نص الإشعار |
| `is_active` | boolean | لا | تفعيل/إيقاف |
| `sort_order` | integer | لا | ≥ 0 |

### مثال طلب

```http
PUT /api/v1/admin/reengagement-reminders/6
Content-Type: application/json

{
  "title": "تحديث العنوان فقط",
  "is_active": false
}
```

### استجابة ناجحة (200)

```json
{
  "success": true,
  "data": {
    "id": 6,
    "amount": 3,
    "unit": "week",
    "title": "تحديث العنوان فقط",
    "body": "مرّ ثلاث أسابيع على آخر زيارة. نرجعك للمسار.",
    "is_active": false,
    "sort_order": 6,
    "created_at": "2026-03-04T14:00:00.000000Z",
    "updated_at": "2026-03-04T15:00:00.000000Z"
  },
  "message": "messages.reengagement_reminder.updated"
}
```

### أخطاء محتملة

- **404** — قاعدة التذكير غير موجودة.
- **422** — خطأ تحقق (نفس قواعد إنشاء الحقول عند إرسالها).

---

## 5. حذف قاعدة تذكير

**`DELETE /reengagement-reminders/{id}`**

حذف قاعدة تذكير نهائياً.

### مثال طلب

```http
DELETE /api/v1/admin/reengagement-reminders/6
```

### استجابة ناجحة (200)

```json
{
  "success": true,
  "message": "messages.reengagement_reminder.deleted"
}
```

### أخطاء محتملة

- **404** — قاعدة التذكير غير موجودة.

---

## ملخص Endpoints

| Method | Endpoint | الوصف |
|--------|----------|--------|
| GET | `/api/v1/admin/reengagement-reminders` | قائمة مع فلترة وترقيم |
| GET | `/api/v1/admin/reengagement-reminders/{id}` | عرض واحد |
| POST | `/api/v1/admin/reengagement-reminders` | إنشاء |
| PUT | `/api/v1/admin/reengagement-reminders/{id}` | تحديث |
| DELETE | `/api/v1/admin/reengagement-reminders/{id}` | حذف |

---

## Placeholders في النص

في حقلي `title` و `body` يمكن استخدام:

- `{{first_name}}` — اسم المستخدم الأول
- `{{amount}}` — قيمة `amount` للقاعدة
- `{{unit_label}}` — تسمية الوحدة بالعربية (يوم/أيام، أسبوع/أسابيع، شهر/أشهر، سنة/سنوات)

يتم استبدالها عند إرسال الإشعار للمستخدم.
