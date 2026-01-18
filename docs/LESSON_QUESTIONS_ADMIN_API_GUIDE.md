# دليل شامل: إدارة أسئلة الدروس - Admin API

## جدول المحتويات
1. [نظرة عامة](#نظرة-عامة)
2. [البنية الأساسية](#البنية-الأساسية)
3. [API Endpoints](#api-endpoints)
4. [التفاصيل التقنية](#التفاصيل-التقنية)
5. [أمثلة عملية](#أمثلة-عملية)
6. [معالجة الأخطاء](#معالجة-الأخطاء)

---

## نظرة عامة

يوفر هذا النظام API كاملة لإدارة أسئلة الدروس من قبل المشرفين (Admin)، ويتضمن:

- **إنشاء أسئلة الدروس** مع خياراتها
- **تحديث الأسئلة** والخيارات
- **حذف الأسئلة**
- **إعادة ترتيب الأسئلة**
- **الفلترة والبحث** في الأسئلة

### أنواع الأسئلة المدعومة

1. **single_choice**: اختيار من متعدد (إجابة واحدة)
2. **multiple_choice**: اختيار من متعدد (إجابات متعددة)
3. **true_false**: صحيح/خطأ
4. **match**: مطابقة (مطابقة المصطلحات بالتعريفات)

---

## البنية الأساسية

### الجداول المستخدمة

#### جدول `lesson_questions`
```sql
id              BIGINT          PRIMARY KEY
lesson_id       BIGINT          الدرس
type            ENUM            نوع السؤال
question_text   TEXT            نص السؤال
attached_path   VARCHAR(100)    مسار الملف المرفق
explanation     TEXT            الشرح
score           DECIMAL(6,2)    النقاط
order_index     BIGINT          الترتيب
created_at      TIMESTAMP
updated_at      TIMESTAMP
deleted_at      TIMESTAMP       Soft Delete
```

#### جدول `lesson_question_options`
```sql
id              BIGINT          PRIMARY KEY
question_id     BIGINT          السؤال
option_text     TEXT            نص الخيار
pair_key        VARCHAR(100)    مفتاح المطابقة (لأسئلة match)
is_correct      TINYINT         هل الخيار صحيح؟
attached_path   VARCHAR(100)    مسار الملف المرفق
order_index     BIGINT          الترتيب
created_at      TIMESTAMP
updated_at      TIMESTAMP
deleted_at      TIMESTAMP       Soft Delete
```

---

## API Endpoints

جميع الـ endpoints تتطلب:
- **Authentication**: `Bearer Token` (Sanctum)
- **Authorization**: صلاحيات Admin
- **Base URL**: `/api/v1/admin/lesson-questions`

### 1. جلب قائمة الأسئلة (Index)

**GET** `/api/v1/admin/lesson-questions`

#### Query Parameters (اختيارية)

| Parameter | Type | Description |
|-----------|------|-------------|
| `lesson_id` | integer | فلترة حسب الدرس |
| `type` | string | فلترة حسب نوع السؤال (single_choice, multiple_choice, true_false, match) |
| `per_page` | integer | عدد النتائج في الصفحة (افتراضي: 20) |
| `page` | integer | رقم الصفحة |
| `sort_field` | string | حقل الترتيب (افتراضي: order_index) |
| `sort_order` | string | اتجاه الترتيب (asc/desc، افتراضي: asc) |
| `search` | string | البحث في question_text و explanation |

#### Response Success (200)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "lesson_id": 10,
      "type": "single_choice",
      "question_text": "ما هي عاصمة فرنسا؟",
      "attached_path": null,
      "explanation": "باريس هي عاصمة فرنسا وأكبر مدنها",
      "score": "1.00",
      "order_index": 1,
      "created_at": "2024-01-15T10:00:00.000000Z",
      "updated_at": "2024-01-15T10:00:00.000000Z",
      "lesson": {
        "id": 10,
        "title": "مقدمة في الجغرافيا"
      },
      "lesson_question_options": [
        {
          "id": 1,
          "option_text": "لندن",
          "is_correct": false,
          "order_index": 1
        },
        {
          "id": 2,
          "option_text": "باريس",
          "is_correct": true,
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

**GET** `/api/v1/admin/lesson-questions/{id}`

#### Response Success (200)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "lesson_id": 10,
    "type": "single_choice",
    "question_text": "ما هي عاصمة فرنسا؟",
    "attached_path": null,
    "explanation": "باريس هي عاصمة فرنسا وأكبر مدنها",
    "score": "1.00",
    "order_index": 1,
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T10:00:00.000000Z",
    "lesson": {
      "id": 10,
      "title": "مقدمة في الجغرافيا"
    },
    "lesson_question_options": [
      {
        "id": 1,
        "question_id": 1,
        "option_text": "لندن",
        "pair_key": null,
        "is_correct": false,
        "attached_path": null,
        "order_index": 1,
        "created_at": "2024-01-15T10:00:00.000000Z",
        "updated_at": "2024-01-15T10:00:00.000000Z"
      },
      {
        "id": 2,
        "question_id": 1,
        "option_text": "باريس",
        "pair_key": null,
        "is_correct": true,
        "attached_path": null,
        "order_index": 2,
        "created_at": "2024-01-15T10:00:00.000000Z",
        "updated_at": "2024-01-15T10:00:00.000000Z"
      }
    ]
  }
}
```

---

### 3. إنشاء سؤال جديد (Create)

**POST** `/api/v1/admin/lesson-questions`

#### Request Body

```json
{
  "lesson_id": 10,
  "type": "single_choice",
  "question_text": "ما هي عاصمة فرنسا؟",
  "attached_path": null,
  "explanation": "باريس هي عاصمة فرنسا وأكبر مدنها",
  "score": 1.0,
  "options": [
    {
      "option_text": "لندن",
      "is_correct": false,
      "attached_path": null
    },
    {
      "option_text": "باريس",
      "is_correct": true,
      "attached_path": null
    },
    {
      "option_text": "برلين",
      "is_correct": false,
      "attached_path": null
    }
  ]
}
```

#### Validation Rules

- `lesson_id`: required, exists in lessons table
- `type`: required, in: single_choice, multiple_choice, true_false, match
- `question_text`: required, string
- `attached_path`: nullable, string, max:100
- `explanation`: nullable, string
- `score`: nullable, numeric, min:0
- `options`: required, array, min:1
- `options.*.option_text`: required, string
- `options.*.pair_key`: nullable, string, max:100 (لأسئلة match)
- `options.*.is_correct`: nullable, boolean
- `options.*.attached_path`: nullable, string, max:100

#### Response Success (201)

```json
{
  "success": true,
  "message": "messages.lesson_question.created",
  "data": {
    "id": 1,
    "lesson_id": 10,
    "type": "single_choice",
    "question_text": "ما هي عاصمة فرنسا؟",
    "order_index": 15,
    "lesson_question_options": [...]
  },
  "status": 201
}
```

---

### 4. تحديث سؤال (Update)

**PUT** `/api/v1/admin/lesson-questions/{id}`

#### Request Body

جميع الحقول اختيارية (`sometimes`) ما عدا الحقول المرتبطة بـ `options`:

```json
{
  "question_text": "ما هي عاصمة فرنسا؟ (محدث)",
  "explanation": "شرح محدث",
  "score": 2.0,
  "options": [
    {
      "id": 1,
      "option_text": "لندن (محدث)",
      "is_correct": false
    },
    {
      "id": 2,
      "option_text": "باريس (محدث)",
      "is_correct": true
    },
    {
      "option_text": "خيار جديد",
      "is_correct": false
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
  "message": "messages.lesson_question.updated",
  "data": {
    "id": 1,
    "question_text": "ما هي عاصمة فرنسا؟ (محدث)",
    "lesson_question_options": [...]
  },
  "status": 200
}
```

---

### 5. حذف سؤال (Delete)

**DELETE** `/api/v1/admin/lesson-questions/{id}`

#### Response Success (200)

```json
{
  "success": true,
  "message": "messages.lesson_question.deleted",
  "status": 200
}
```

**ملاحظة**: يتم حذف جميع الخيارات المرتبطة بالسؤال تلقائياً.

---

### 6. إعادة ترتيب سؤال (Reorder)

**PUT** `/api/v1/admin/lesson-questions/{id}/reorder`

#### Request Body

```json
{
  "new_order_index": 5
}
```

#### Validation Rules

- `new_order_index`: required, integer, min:1

#### Response Success (200)

```json
{
  "success": true,
  "message": "messages.lesson_question.reordered",
  "data": [...],
  "meta": {...},
  "status": 200
}
```

---

## التفاصيل التقنية

### أنواع الأسئلة والخصائص

#### 1. single_choice (اختيار من متعدد - إجابة واحدة)

- يجب أن يكون خيار واحد فقط `is_correct: true`
- باقي الخيارات يجب أن تكون `is_correct: false`

**مثال:**
```json
{
  "type": "single_choice",
  "options": [
    {"option_text": "لندن", "is_correct": false},
    {"option_text": "باريس", "is_correct": true},
    {"option_text": "برلين", "is_correct": false}
  ]
}
```

#### 2. multiple_choice (اختيار من متعدد - إجابات متعددة)

- يمكن أن يكون هناك أكثر من خيار `is_correct: true`

**مثال:**
```json
{
  "type": "multiple_choice",
  "options": [
    {"option_text": "لندن", "is_correct": true},
    {"option_text": "باريس", "is_correct": true},
    {"option_text": "برلين", "is_correct": false}
  ]
}
```

#### 3. true_false (صحيح/خطأ)

- يجب أن يكون خيارين فقط
- خيار واحد `is_correct: true` والآخر `is_correct: false`

**مثال:**
```json
{
  "type": "true_false",
  "question_text": "باريس هي عاصمة فرنسا",
  "options": [
    {"option_text": "صحيح", "is_correct": true},
    {"option_text": "خطأ", "is_correct": false}
  ]
}
```

#### 4. match (مطابقة)

- الخيارات اليمينية (المصطلحات) لها `pair_key` مثل "L1", "L2"
- الخيارات اليسارية (التعريفات) ليس لها `pair_key` (null)
- `is_correct` غير مطلوب لأسئلة match

**مثال:**
```json
{
  "type": "match",
  "question_text": "طابق المصطلحات بالتعريفات",
  "options": [
    {"option_text": "دبلوماسية", "pair_key": "L1", "order_index": 1},
    {"option_text": "قنصل", "pair_key": "L2", "order_index": 2},
    {"option_text": "فن التفاوض", "pair_key": null, "order_index": 3},
    {"option_text": "ممثل دولة في دولة أخرى", "pair_key": null, "order_index": 4}
  ]
}
```

---

## أمثلة عملية

### مثال 1: إنشاء سؤال single_choice

**Request:**
```http
POST /api/v1/admin/lesson-questions
Content-Type: application/json
Authorization: Bearer {token}

{
  "lesson_id": 10,
  "type": "single_choice",
  "question_text": "ما هي عاصمة فرنسا؟",
  "explanation": "باريس هي عاصمة فرنسا وأكبر مدنها",
  "score": 1.0,
  "options": [
    {"option_text": "لندن", "is_correct": false},
    {"option_text": "باريس", "is_correct": true},
    {"option_text": "برلين", "is_correct": false},
    {"option_text": "مدريد", "is_correct": false}
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "messages.lesson_question.created",
  "data": {
    "id": 25,
    "lesson_id": 10,
    "type": "single_choice",
    "question_text": "ما هي عاصمة فرنسا؟",
    "order_index": 1,
    "lesson_question_options": [
      {"id": 101, "option_text": "لندن", "is_correct": false},
      {"id": 102, "option_text": "باريس", "is_correct": true},
      {"id": 103, "option_text": "برلين", "is_correct": false},
      {"id": 104, "option_text": "مدريد", "is_correct": false}
    ]
  }
}
```

---

### مثال 2: إنشاء سؤال match

**Request:**
```http
POST /api/v1/admin/lesson-questions
Content-Type: application/json
Authorization: Bearer {token}

{
  "lesson_id": 10,
  "type": "match",
  "question_text": "طابق المصطلحات بالتعريفات",
  "score": 2.0,
  "options": [
    {"option_text": "دبلوماسية", "pair_key": "L1"},
    {"option_text": "قنصل", "pair_key": "L2"},
    {"option_text": "فن التفاوض", "pair_key": null},
    {"option_text": "ممثل دولة في دولة أخرى", "pair_key": null}
  ]
}
```

---

### مثال 3: تحديث سؤال مع تحديث خياراته

**Request:**
```http
PUT /api/v1/admin/lesson-questions/25
Content-Type: application/json
Authorization: Bearer {token}

{
  "question_text": "ما هي عاصمة فرنسا؟ (محدث)",
  "score": 2.0,
  "options": [
    {"id": 101, "option_text": "لندن (محدث)", "is_correct": false},
    {"id": 102, "option_text": "باريس (محدث)", "is_correct": true},
    {"option_text": "روما (جديد)", "is_correct": false}
  ]
}
```

**ملاحظة**: الخيار الذي له `id: 103` و `id: 104` سيتم حذفه تلقائياً لأنه لم يتم إرساله.

---

### مثال 4: البحث والفلترة

**Request:**
```http
GET /api/v1/admin/lesson-questions?lesson_id=10&type=single_choice&per_page=10&page=1&search=فرنسا
Authorization: Bearer {token}
```

---

### مثال 5: إعادة ترتيب سؤال

**Request:**
```http
PUT /api/v1/admin/lesson-questions/25/reorder
Content-Type: application/json
Authorization: Bearer {token}

{
  "new_order_index": 5
}
```

---

## معالجة الأخطاء

### Error Responses

#### 404 - السؤال غير موجود

```json
{
  "success": false,
  "message": "messages.lesson_question.not_found",
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

#### 422 - Validation Error

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "lesson_id": ["The lesson id field is required."],
    "type": ["The selected type is invalid."],
    "options": ["The options field is required."]
  },
  "status": 422
}
```

#### 404 - الدرس غير موجود

```json
{
  "success": false,
  "message": "messages.lesson.not_found",
  "status": 404
}
```

---

## ملاحظات مهمة للفرونت إند

### 1. الترتيب التلقائي (order_index)

- عند إنشاء سؤال جديد، يتم تعيين `order_index` تلقائياً
- يمكن إعادة ترتيب الأسئلة باستخدام endpoint `reorder`

### 2. تحديث الخيارات

- عند تحديث سؤال مع إرسال `options`:
  - الخيارات مع `id` يتم تحديثها
  - الخيارات بدون `id` يتم إنشاؤها
  - الخيارات الموجودة غير المدرجة في القائمة يتم حذفها

### 3. البحث والفلترة

- يمكن البحث في `question_text` و `explanation`
- يمكن الفلترة حسب `lesson_id` و `type`
- يمكن التصنيف حسب أي حقل باستخدام `sort_field`

### 4. العلاقات

- عند جلب السؤال، يتم تضمين `lesson` و `lesson_question_options` تلقائياً
- العلاقات متاحة عبر `LessonQuestionResource`

---

## الخلاصة

يوفر هذا النظام API كاملة وسهلة الاستخدام لإدارة أسئلة الدروس، مع دعم جميع أنواع الأسئلة والعمليات الأساسية (CRUD) وإعادة الترتيب.

للأسئلة أو الاستفسارات، يرجى الرجوع إلى الكود المصدري أو التواصل مع فريق التطوير.
