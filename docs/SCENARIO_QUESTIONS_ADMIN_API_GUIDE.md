# دليل شامل: إدارة أسئلة السيناريوهات - Admin API

> ملاحظة تحديث: تم اعتماد `single_choice` كسلوك تشغيلي للسيناريوهات، مع إضافة `feedback_text` و`next_question_code`.
> للحصول على الدليل التطبيقي الكامل للداش بورد بعد التحديثات الأخيرة، راجع:
> `docs/SCENARIO_DASHBOARD_DEVELOPER_GUIDE_AR.md`

## جدول المحتويات
1. [نظرة عامة](#نظرة-عامة)
2. [البنية الأساسية](#البنية-الأساسية)
3. [آلية عمل التدفق الديناميكي](#آلية-عمل-التدفق-الديناميكي)
4. [API Endpoints](#api-endpoints)
5. [التفاصيل التقنية](#التفاصيل-التقنية)
6. [التحقق من سلامة التدفق](#التحقق-من-سلامة-التدفق)
7. [أمثلة عملية](#أمثلة-عملية)
8. [معالجة الأخطاء](#معالجة-الأخطاء)

---

## نظرة عامة

يوفر هذا النظام API كاملة لإدارة أسئلة السيناريوهات من قبل المشرفين (Admin)، مع آلية تحقق تلقائي من سلامة التدفق لضمان عدم وجود حلقات لا نهائية أو طرق مسدودة.

### المميزات الرئيسية

- **إنشاء أسئلة السيناريوهات** مع خياراتها وربطها بالسؤال التالي
- **تحديث الأسئلة** والخيارات مع الحفاظ على سلامة التدفق
- **حذف الأسئلة** مع التحقق من عدم تأثير الحذف على السلسلة
- **التحقق التلقائي** من سلامة التدفق (لا حلقات لا نهائية، لا طرق مسدودة)
- **السماح بالحلقات** (الرجوع للبداية لإعادة التفكير)
- **الفلترة والبحث** في الأسئلة

### أنواع الأسئلة المدعومة

1. **single_choice**: اختيار من متعدد (إجابة واحدة)
2. **true_false**: صحيح/خطأ (خياران فقط)

**ملاحظة**: لا يوجد `is_correct` في أسئلة السيناريوهات. كل خيار يحدد السؤال التالي عبر `next_question_id`.

---

## البنية الأساسية

### الجداول المستخدمة

#### جدول `scenario_questions`
```sql
id              BIGINT          PRIMARY KEY
scenario_id     BIGINT          السيناريو
code            VARCHAR(20)     رمز السؤال (فريد داخل السيناريو)
type            ENUM            نوع السؤال (single_choice, true_false)
question_text   TEXT            نص السؤال
attached_path   VARCHAR(100)    مسار الملف المرفق
explanation     TEXT            الشرح
order_index     BIGINT          الترتيب
created_at      TIMESTAMP
updated_at      TIMESTAMP
deleted_at      TIMESTAMP       Soft Delete
```

#### جدول `scenario_question_options`
```sql
id              BIGINT          PRIMARY KEY
question_id     BIGINT          السؤال
option_text     TEXT            نص الخيار
next_question_id BIGINT         السؤال التالي (NULL = نهاية السيناريو)
attached_path   VARCHAR(100)    مسار الملف المرفق
order_index     BIGINT          الترتيب
created_at      TIMESTAMP
updated_at      TIMESTAMP
deleted_at      TIMESTAMP       Soft Delete
```

#### جدول `scenarios`
```sql
id                  BIGINT          PRIMARY KEY
start_question_id   BIGINT          السؤال الأول (بداية السيناريو)
...
```

---

## آلية عمل التدفق الديناميكي

### كيف يعمل النظام؟

1. **بداية السيناريو**: يبدأ السيناريو من `start_question_id` المحدد في جدول `scenarios`
2. **اختيار الإجابة**: المستخدم يختار إجابة (خيار)
3. **السؤال التالي**: يتم الانتقال للسؤال المحدد في `next_question_id` لهذا الخيار
4. **نهاية السيناريو**: إذا كان `next_question_id = null`، ينتهي السيناريو

### مثال على التدفق

```
السؤال 1 (start_question_id)
  ├─ الخيار A → السؤال 2
  └─ الخيار B → السؤال 1 (رجوع للبداية لإعادة التفكير)

السؤال 2
  ├─ الخيار X → السؤال 3
  └─ الخيار Y → null (نهاية السيناريو)

السؤال 3
  ├─ الخيار P → null (نهاية السيناريو)
  └─ الخيار Q → السؤال 1 (رجوع للبداية)
```

### المبادئ المهمة

1. **✅ السماح بالحلقات (Loops)**: يمكن الرجوع للسؤال الأول أو أي سؤال تم زيارته مسبقاً
2. **✅ السماح بإعادة التفكير**: المستخدم قد يختار جواب مختلف ويعود للسؤال الأول
3. **❌ منع Deadlock**: منع الحلقات المغلقة تماماً (كل الخيارات تشير لنفس السؤال)
4. **❌ منع الأسئلة المعزولة**: جميع الأسئلة يجب أن تكون قابلة للوصول من `start_question_id`
5. **✅ التحقق من وجود مسار للخروج**: يجب أن يكون هناك على الأقل خيار واحد `next_question_id = null`

---

## API Endpoints

جميع الـ endpoints تتطلب:
- **Authentication**: `Bearer Token` (Sanctum)
- **Authorization**: صلاحيات Admin
- **Base URL**: `/api/v1/admin/scenario-questions`

### 1. جلب قائمة الأسئلة (Index)

**GET** `/api/v1/admin/scenario-questions`

#### Query Parameters (اختيارية)

| Parameter | Type | Description |
|-----------|------|-------------|
| `scenario_id` | integer | فلترة حسب السيناريو |
| `type` | string | فلترة حسب نوع السؤال (single_choice, true_false) |
| `code` | string | البحث حسب رمز السؤال |
| `per_page` | integer | عدد النتائج في الصفحة (افتراضي: 20) |
| `page` | integer | رقم الصفحة |
| `sort_field` | string | حقل الترتيب (افتراضي: order_index) |
| `sort_order` | string | اتجاه الترتيب (asc/desc، افتراضي: asc) |
| `search` | string | البحث في question_text, explanation, code |

#### Response Success (200)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "scenario_id": 10,
      "code": "Q1",
      "type": "single_choice",
      "question_text": "ما هو قرارك الأول؟",
      "attached_path": null,
      "explanation": "شرح بعد الإجابة",
      "order_index": 1,
      "created_at": "2024-01-15T10:00:00.000000Z",
      "updated_at": "2024-01-15T10:00:00.000000Z",
      "scenario": {
        "id": 10,
        "title": "سيناريو التفاوض"
      },
      "scenario_question_options": [
        {
          "id": 1,
          "option_text": "الخيار الأول",
          "next_question_id": 2,
          "order_index": 1
        },
        {
          "id": 2,
          "option_text": "الخيار الثاني",
          "next_question_id": 1,
          "order_index": 2
        }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

---

### 2. جلب سؤال محدد (Show)

**GET** `/api/v1/admin/scenario-questions/{id}`

#### Response Success (200)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "scenario_id": 10,
    "code": "Q1",
    "type": "single_choice",
    "question_text": "ما هو قرارك الأول؟",
    "attached_path": null,
    "explanation": "شرح بعد الإجابة",
    "order_index": 1,
    "scenario": {...},
    "scenario_question_options": [
      {
        "id": 1,
        "question_id": 1,
        "option_text": "الخيار الأول",
        "next_question_id": 2,
        "attached_path": null,
        "order_index": 1
      }
    ]
  }
}
```

---

### 3. إنشاء سؤال جديد (Create)

**POST** `/api/v1/admin/scenario-questions`

#### Request Body

```json
{
  "scenario_id": 10,
  "code": "Q1",
  "type": "single_choice",
  "question_text": "ما هو قرارك الأول؟",
  "attached_path": null,
  "explanation": "شرح بعد الإجابة",
  "options": [
    {
      "option_text": "الخيار الأول",
      "next_question_id": 2,
      "attached_path": null
    },
    {
      "option_text": "الخيار الثاني",
      "next_question_id": 1,
      "attached_path": null
    },
    {
      "option_text": "إنهاء السيناريو",
      "next_question_id": null,
      "attached_path": null
    }
  ]
}
```

#### Validation Rules

- `scenario_id`: required, exists in scenarios table
- `code`: required, string, max:20 (فريد داخل السيناريو)
- `type`: required, in: single_choice, true_false
- `question_text`: required, string
- `attached_path`: nullable, string, max:100
- `explanation`: nullable, string
- `options`: required, array, min:2
- `options.*.option_text`: required, string
- `options.*.next_question_id`: nullable, exists:scenario_questions,id
- `options.*.attached_path`: nullable, string, max:100

#### Response Success (201)

```json
{
  "success": true,
  "message": "messages.scenario_question.created",
  "data": {
    "id": 1,
    "code": "Q1",
    "scenario_question_options": [...]
  },
  "status": 201
}
```

**ملاحظة مهمة**: يتم التحقق تلقائياً من سلامة التدفق بعد الإنشاء. إذا فشل التحقق، يتم حذف السؤال تلقائياً وإرجاع خطأ.

---

### 4. تحديث سؤال (Update)

**PUT** `/api/v1/admin/scenario-questions/{id}`

#### Request Body

جميع الحقول اختيارية (`sometimes`):

```json
{
  "code": "Q1_UPDATED",
  "question_text": "ما هو قرارك الأول؟ (محدث)",
  "explanation": "شرح محدث",
  "options": [
    {
      "id": 1,
      "option_text": "الخيار الأول (محدث)",
      "next_question_id": 2
    },
    {
      "id": 2,
      "option_text": "الخيار الثاني (محدث)",
      "next_question_id": 1
    },
    {
      "option_text": "خيار جديد",
      "next_question_id": null
    }
  ]
}
```

**ملاحظة مهمة**: 
- إذا أرسلت `options`، يجب أن تتضمن جميع الخيارات المطلوبة
- الخيارات التي تحتوي على `id` سيتم تحديثها
- الخيارات بدون `id` سيتم إنشاؤها كخيارات جديدة
- الخيارات الموجودة التي لم يتم إرسالها سيتم حذفها

#### Response Success (200)

```json
{
  "success": true,
  "message": "messages.scenario_question.updated",
  "data": {...},
  "status": 200
}
```

**ملاحظة مهمة**: يتم التحقق تلقائياً من سلامة التدفق بعد التحديث. إذا فشل التحقق، يتم إرجاع خطأ (التحديثات لا تتم التراجع عنها).

---

### 5. حذف سؤال (Delete)

**DELETE** `/api/v1/admin/scenario-questions/{id}`

#### Response Success (200)

```json
{
  "success": true,
  "message": "messages.scenario_question.deleted",
  "status": 200
}
```

**ملاحظات**:
- يتم التحقق من أن السؤال ليس `start_question_id` للسيناريو
- يتم التحقق من عدم وجود خيارات تشير له (next_question_id)
- إذا كان مرتبطاً، يتم إرجاع خطأ ومنع الحذف

---

### 6. إعادة ترتيب سؤال (Reorder)

**PUT** `/api/v1/admin/scenario-questions/{id}/reorder`

#### Request Body

```json
{
  "new_order_index": 5
}
```

#### Response Success (200)

```json
{
  "success": true,
  "message": "messages.scenario_question.reordered",
  "data": [...],
  "meta": {...},
  "status": 200
}
```

---

## التفاصيل التقنية

### أنواع الأسئلة والخصائص

#### 1. single_choice (اختيار من متعدد - إجابة واحدة)

- يجب أن يكون هناك خياران على الأقل
- كل خيار يحدد السؤال التالي عبر `next_question_id`

**مثال:**
```json
{
  "type": "single_choice",
  "options": [
    {"option_text": "الخيار 1", "next_question_id": 2},
    {"option_text": "الخيار 2", "next_question_id": 3},
    {"option_text": "إنهاء", "next_question_id": null}
  ]
}
```

#### 2. true_false (صحيح/خطأ)

- يجب أن يكون خيارين فقط
- كل خيار يحدد السؤال التالي عبر `next_question_id`

**مثال:**
```json
{
  "type": "true_false",
  "question_text": "باريس هي عاصمة فرنسا",
  "options": [
    {"option_text": "صحيح", "next_question_id": 2},
    {"option_text": "خطأ", "next_question_id": 1}
  ]
}
```

---

## التحقق من سلامة التدفق

### المبادئ

1. **✅ السماح بالحلقات**: يمكن الرجوع للسؤال الأول أو أي سؤال
2. **❌ منع Deadlock**: منع الحلقات المغلقة تماماً (كل الخيارات تشير لنفس السؤال)
3. **❌ منع الأسئلة المعزولة**: جميع الأسئلة قابلة للوصول من `start_question_id`
4. **✅ التحقق من وجود مسار للخروج**: يجب أن يكون هناك `next_question_id = null` في بعض الخيارات

### آلية التحقق

يتم التحقق تلقائياً عند:
- **إنشاء سؤال جديد**: إذا فشل التحقق، يتم حذف السؤال تلقائياً
- **تحديث سؤال**: إذا فشل التحقق، يتم إرجاع خطأ (لا تراجع عن التحديثات)

### حالات الفشل المحتملة

1. **لا يوجد start_question_id**: السيناريو لا يحتوي على سؤال بداية
2. **لا يوجد أسئلة**: السيناريو فارغ
3. **لا يوجد مسار للخروج**: جميع الخيارات تشير لأسئلة (لا يوجد `next_question_id = null`)
4. **أسئلة غير قابلة للوصول**: أسئلة لا يمكن الوصول إليها من `start_question_id`
5. **Deadlock**: سؤال كل خياراته تشير لنفس السؤال
6. **next_question_id غير صحيح**: يشير لسؤال غير موجود في السيناريو

---

## أمثلة عملية

### مثال 1: إنشاء سؤال single_choice مع تدفق بسيط

**Request:**
```http
POST /api/v1/admin/scenario-questions
Content-Type: application/json
Authorization: Bearer {token}

{
  "scenario_id": 10,
  "code": "Q1",
  "type": "single_choice",
  "question_text": "ما هو قرارك الأول؟",
  "options": [
    {"option_text": "اختيار A", "next_question_id": 2},
    {"option_text": "اختيار B", "next_question_id": 3},
    {"option_text": "إنهاء", "next_question_id": null}
  ]
}
```

---

### مثال 2: إنشاء سؤال true_false مع رجوع للبداية

**Request:**
```http
POST /api/v1/admin/scenario-questions
Content-Type: application/json
Authorization: Bearer {token}

{
  "scenario_id": 10,
  "code": "Q2",
  "type": "true_false",
  "question_text": "هل أنت متأكد من قرارك؟",
  "explanation": "يمكنك إعادة التفكير",
  "options": [
    {"option_text": "صحيح", "next_question_id": 3},
    {"option_text": "خطأ، أريد إعادة التفكير", "next_question_id": 1}
  ]
}
```

---

### مثال 3: تحديث سؤال مع تحديث خياراته

**Request:**
```http
PUT /api/v1/admin/scenario-questions/25
Content-Type: application/json
Authorization: Bearer {token}

{
  "question_text": "ما هو قرارك الأول؟ (محدث)",
  "options": [
    {"id": 101, "option_text": "اختيار A (محدث)", "next_question_id": 2},
    {"id": 102, "option_text": "اختيار B (محدث)", "next_question_id": 3},
    {"option_text": "خيار جديد", "next_question_id": null}
  ]
}
```

---

### مثال 4: البحث والفلترة

**Request:**
```http
GET /api/v1/admin/scenario-questions?scenario_id=10&type=single_choice&per_page=10&page=1&search=قرار
Authorization: Bearer {token}
```

---

## معالجة الأخطاء

### Error Responses

#### 422 - Validation Error / Flow Validation Failed

```json
{
  "success": false,
  "message": "messages.scenario.no_exit_path",
  "status": 422
}
```

**حالات الخطأ المحتملة:**
- `messages.scenario.no_start_question`: لا يوجد سؤال بداية
- `messages.scenario.no_questions`: لا يوجد أسئلة في السيناريو
- `messages.scenario.no_exit_path`: لا يوجد خيار يؤدي لنهاية السيناريو
- `messages.scenario.unreachable_questions`: أسئلة غير قابلة للوصول
- `messages.scenario.deadlock_question`: سؤال يشكل deadlock
- `messages.scenario.invalid_next_question`: next_question_id غير صحيح
- `messages.scenario_question.code_exists`: رمز السؤال موجود مسبقاً
- `messages.scenario_question.cannot_delete_start_question`: لا يمكن حذف سؤال البداية
- `messages.scenario_question.cannot_delete_referenced`: لا يمكن حذف سؤال يُشار إليه

#### 404 - السؤال غير موجود

```json
{
  "success": false,
  "message": "messages.scenario_question.not_found",
  "status": 404
}
```

#### 403 - لا توجد صلاحيات

```json
{
  "success": false,
  "message": "messages.permission.error",
  "status": 403
}
```

---

## ملاحظات مهمة للفرونت إند

### 1. التحقق التلقائي من سلامة التدفق

- يتم التحقق تلقائياً عند الإنشاء والتحديث
- في حالة فشل التحقق، يتم إرجاع رسالة خطأ واضحة
- يُنصح بعرض رسائل الخطأ بشكل واضح للمستخدم

### 2. تحديث الخيارات

- عند تحديث سؤال مع إرسال `options`:
  - الخيارات مع `id` يتم تحديثها
  - الخيارات بدون `id` يتم إنشاؤها
  - الخيارات الموجودة غير المدرجة في القائمة يتم حذفها

### 3. حقل `code`

- يجب أن يكون فريداً داخل السيناريو
- يُستخدم للتعريف عن السؤال بشكل سريع (مثل "Q1", "Q2")
- يتم التحقق من عدم التكرار تلقائياً

### 4. `next_question_id`

- إذا كان `null`: يعني نهاية السيناريو
- يجب أن يشير لسؤال موجود في نفس السيناريو
- يمكن أن يشير لـ `start_question_id` (السماح بالرجوع للبداية)

### 5. `start_question_id` في Scenario

- يجب تحديده في السيناريو قبل البدء بإضافة الأسئلة
- لا يمكن حذف السؤال المحدد كـ `start_question_id`

### 6. العلاقات

- عند جلب السؤال، يتم تضمين `scenario` و `scenario_question_options` تلقائياً
- `nextQuestion` متاح في `scenario_question_options` إذا تم تحميله

---

## الخلاصة

يوفر هذا النظام API كاملة وسهلة الاستخدام لإدارة أسئلة السيناريوهات، مع آلية تحقق تلقائي من سلامة التدفق تسمح بالحلقات والرجوع للبداية مع منع المشاكل المحتملة مثل deadlock والأسئلة المعزولة.

للأسئلة أو الاستفسارات، يرجى الرجوع إلى الكود المصدري أو التواصل مع فريق التطوير.
