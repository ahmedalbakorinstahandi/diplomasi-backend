# دليل شامل: نظام أسئلة الدروس - للموبايل

## جدول المحتويات
1. [نظرة عامة](#نظرة-عامة)
2. [بنية قاعدة البيانات](#بنية-قاعدة-البيانات)
3. [دورة حياة المحاولة](#دورة-حياة-المحاولة)
4. [APIs التفصيلية](#apis-التفصيلية)
5. [أنواع الأسئلة](#أنواع-الأسئلة)
6. [أمثلة عملية كاملة](#أمثلة-عملية-كاملة)
7. [حالات خاصة ومعالجة الأخطاء](#حالات-خاصة-ومعالجة-الأخطاء)

---

## نظرة عامة

### ما هو النظام؟
نظام متكامل لإدارة أسئلة الدروس يتيح للمستخدمين:
- بدء محاولة للإجابة على أسئلة درس معين
- جلب جميع الأسئلة مع معرفة حالة كل سؤال (لم يُجاب، تم الإجابة، السؤال الحالي)
- الإجابة على الأسئلة بجميع أنواعها
- تتبع التقدم في المحاولة
- إعادة المحاولة (بدء محاولة جديدة)

### المكونات الرئيسية
1. **المحاولة (Attempt)**: تمثل جلسة إجابة المستخدم على أسئلة درس معين
2. **السؤال (Question)**: سؤال في الدرس
3. **الإجابة (Answer)**: إجابة المستخدم على سؤال معين
4. **الخيارات (Options)**: الخيارات المتاحة لكل سؤال

---

## بنية قاعدة البيانات

### 1. جدول `user_lesson_attempts` (المحاولات)

```sql
id                  BIGINT          PRIMARY KEY
user_id             BIGINT          المستخدم
lesson_id           BIGINT          الدرس
status              ENUM            الحالة: 'in_progress' | 'finished'
score               DECIMAL(6,2)    النتيجة النهائية (نسبة مئوية)
current_question_id BIGINT          السؤال الحالي (أين وصل المستخدم)
started_at          TIMESTAMP       وقت البدء
finished_at         TIMESTAMP       وقت الانتهاء (NULL إذا لم ينته)
total_time          INT             الوقت الإجمالي بالثواني
created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP       Soft Delete
```

**مثال على سجل:**
```
id: 1
user_id: 5
lesson_id: 10
status: 'in_progress'
score: 0.00
current_question_id: 25
started_at: '2024-01-15 10:30:00'
finished_at: NULL
total_time: NULL
```

### 2. جدول `lesson_questions` (الأسئلة)

```sql
id              BIGINT          PRIMARY KEY
lesson_id       BIGINT          الدرس
type            ENUM            نوع السؤال: 'single_choice' | 'multiple_choice' | 'true_false' | 'match'
question_text   TEXT            نص السؤال
attached_path   VARCHAR(100)    مسار الملف المرفق (صورة/فيديو)
explanation     TEXT            الشرح (يظهر بعد الإجابة)
score           DECIMAL(6,2)    النقاط
order_index     BIGINT          ترتيب السؤال
created_at      TIMESTAMP
updated_at      TIMESTAMP
deleted_at      TIMESTAMP       Soft Delete
```

**مثال على سجل:**
```
id: 25
lesson_id: 10
type: 'single_choice'
question_text: 'ما هي عاصمة فرنسا؟'
attached_path: NULL
explanation: 'باريس هي عاصمة فرنسا وأكبر مدنها'
score: 1.00
order_index: 1
```

### 3. جدول `lesson_question_options` (خيارات الأسئلة)

```sql
id              BIGINT          PRIMARY KEY
question_id     BIGINT          السؤال
option_text     TEXT            نص الخيار
pair_key        VARCHAR(100)    مفتاح المطابقة (لأسئلة match)
is_correct      TINYINT         هل الخيار صحيح؟ (0 أو 1)
attached_path   VARCHAR(100)    مسار الملف المرفق
order_index     BIGINT          ترتيب الخيار
created_at      TIMESTAMP
updated_at      TIMESTAMP
deleted_at      TIMESTAMP       Soft Delete
```

**أمثلة:**

**لـ single_choice:**
```
id: 101, question_id: 25, option_text: 'لندن', is_correct: 0, order_index: 1
id: 102, question_id: 25, option_text: 'باريس', is_correct: 1, order_index: 2
id: 103, question_id: 25, option_text: 'برلين', is_correct: 0, order_index: 3
id: 104, question_id: 25, option_text: 'مدريد', is_correct: 0, order_index: 4
```

**لـ match:**
```
id: 201, question_id: 30, option_text: 'مصطلح 1', pair_key: 'L1', is_correct: NULL, order_index: 1
id: 202, question_id: 30, option_text: 'مصطلح 2', pair_key: 'L2', is_correct: NULL, order_index: 2
id: 203, question_id: 30, option_text: 'تعريف 1', pair_key: 'L1', is_correct: NULL, order_index: 3
id: 204, question_id: 30, option_text: 'تعريف 2', pair_key: 'L2', is_correct: NULL, order_index: 4
```

### 4. جدول `user_lesson_question_answers` (الإجابات)

```sql
id              BIGINT          PRIMARY KEY
attempt_id      BIGINT          المحاولة
question_id     BIGINT          السؤال
step_index      INT             ترتيب الإجابة (1, 2, 3...)
is_correct      TINYINT         هل الإجابة صحيحة؟ (0 أو 1)
score           DECIMAL(6,2)    النقاط المحصلة
time_spent      INT             الوقت المستغرق بالثواني
answered_at     TIMESTAMP       وقت الإجابة
created_at      TIMESTAMP
updated_at      TIMESTAMP
deleted_at      TIMESTAMP       Soft Delete
```

**مثال:**
```
id: 501
attempt_id: 1
question_id: 25
step_index: 1
is_correct: 1
score: 1.00
time_spent: 30
answered_at: '2024-01-15 10:30:30'
```

### 5. جدول `user_lesson_answer_options` (خيارات الإجابة)

```sql
id                  BIGINT          PRIMARY KEY
user_answer_id      BIGINT          الإجابة
option_id           BIGINT          الخيار المختار
is_correct          TINYINT         هل الخيار صحيح؟
```

**يستخدم لـ:** `single_choice`, `multiple_choice`, `true_false`

**مثال:**
```
id: 601
user_answer_id: 501
option_id: 102
is_correct: 1
```

### 6. جدول `user_lesson_answer_matches` (مطابقات الإجابة)

```sql
id                  BIGINT          PRIMARY KEY
user_answer_id      BIGINT          الإجابة
left_option_id      BIGINT          الخيار الأيسر
right_option_id     BIGINT          الخيار الأيمن
is_correct          TINYINT         هل المطابقة صحيحة؟
```

**يستخدم لـ:** `match`

**مثال:**
```
id: 701
user_answer_id: 502
left_option_id: 201
right_option_id: 203
is_correct: 1
```

---

## دورة حياة المحاولة

### المرحلة 1: بدء المحاولة
```
المستخدم يفتح الدرس
    ↓
الموبايل يستدعي: POST /lessons/{lessonId}/start-attempt
    ↓
الخادم يتحقق:
    - هل يوجد محاولة قائمة (in_progress)؟
        - نعم → إرجاع المحاولة الموجودة
        - لا → إنشاء محاولة جديدة
            - current_question_id = أول سؤال
            - status = 'in_progress'
            - score = 0
    ↓
الموبايل يحصل على: attempt_id, current_question_id
```

### المرحلة 2: جلب الأسئلة مع الحالات
```
الموبايل يستدعي: GET /lessons/{lessonId}/questions?attempt_id={attemptId}
    ↓
الخادم:
    1. يجلب جميع أسئلة الدرس مرتبة
    2. يجلب جميع إجابات المحاولة
    3. يحدد حالة كل سؤال:
        - إذا كان current_question_id → 'current'
        - إذا وجدت إجابة → 'answered'
        - غير ذلك → 'not_answered'
    ↓
الموبايل يحصل على: قائمة بجميع الأسئلة مع حالاتها + معلومات التقدم
```

### المرحلة 3: عرض السؤال الحالي
```
الموبايل يستدعي: GET /lessons/{lessonId}/attempts/{attemptId}/current-question
    ↓
الخادم:
    1. يجلب السؤال الحالي مع جميع الخيارات
    2. يتحقق: هل تم الإجابة على السؤال؟
        - لا → إخفاء is_correct من الخيارات
        - نعم → إظهار is_correct + explanation
    ↓
الموبايل يعرض السؤال للمستخدم
```

### المرحلة 4: إرسال الإجابة
```
المستخدم يختار إجابة
    ↓
الموبايل يستدعي: POST /lessons/{lessonId}/attempts/{attemptId}/submit-answer
    Body: {
        question_id: 25,
        option_id: 102  // حسب نوع السؤال
    }
    ↓
الخادم:
    1. التحقق من صحة المحاولة والسؤال
    2. التحقق من أن السؤال لم يُجاب عليه مسبقاً
    3. التحقق من أن هذا هو السؤال الحالي
    4. معالجة الإجابة حسب نوع السؤال
    5. حفظ الإجابة في قاعدة البيانات
    6. حساب النتيجة (is_correct, score)
    7. تحديث current_question_id إلى السؤال التالي
    8. إذا كان آخر سؤال → status = 'finished'
    ↓
الموبايل يحصل على:
    - is_correct
    - score
    - explanation
    - next_question_id (أو null)
    - attempt_finished (true/false)
```

### المرحلة 5: الانتقال للسؤال التالي
```
إذا attempt_finished = false:
    الموبايل يستدعي: GET /lessons/{lessonId}/attempts/{attemptId}/current-question
    (لجلب السؤال التالي)
    
إذا attempt_finished = true:
    الموبايل يعرض النتيجة النهائية
```

### المرحلة 6: إنهاء المحاولة (اختياري)
```
المستخدم يضغط "إنهاء المحاولة"
    ↓
الموبايل يستدعي: POST /lessons/{lessonId}/attempts/{attemptId}/finish
    ↓
الخادم:
    1. حساب النتيجة النهائية
    2. تحديث status = 'finished'
    3. تحديث finished_at
    ↓
الموبايل يحصل على: النتيجة النهائية + الإحصائيات
```

---

## APIs التفصيلية

### 1. بدء المحاولة

**Endpoint:** `POST /api/v1/user/lessons/{lessonId}/start-attempt`

**Headers:**
```
Authorization: Bearer {token}
```

**Parameters:**
- `lessonId` (path parameter): معرف الدرس

**Response (200 OK):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "user_id": 5,
        "lesson_id": 10,
        "status": "in_progress",
        "score": 0.00,
        "current_question_id": 25,
        "started_at": "2024-01-15T10:30:00.000000Z",
        "finished_at": null,
        "total_time": null,
        "progress": {
            "answered": 0,
            "total": 5,
            "percentage": 0
        }
    }
}
```

**حالات الخطأ:**
- `404`: الدرس غير موجود
- `401`: غير مصرح

**ملاحظات:**
- إذا كان هناك محاولة قائمة (in_progress)، يتم إرجاعها
- إذا لم تكن هناك محاولة، يتم إنشاء واحدة جديدة
- `current_question_id` يشير إلى أول سؤال في الدرس

---

### 2. جلب جميع الأسئلة مع الحالات

**Endpoint:** `GET /api/v1/user/lessons/{lessonId}/questions?attempt_id={attemptId}`

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `attempt_id` (optional): معرف المحاولة. إذا لم يتم إرساله، لن تظهر الحالات

**Response (200 OK):**
```json
{
    "success": true,
    "data": {
        "questions": [
            {
                "id": 25,
                "type": "single_choice",
                "question_text": "ما هي عاصمة فرنسا؟",
                "attached_path": null,
                "order_index": 1,
                "status": "current",
                "user_answer": null
            },
            {
                "id": 26,
                "type": "multiple_choice",
                "question_text": "اختر المدن الأوروبية:",
                "attached_path": null,
                "order_index": 2,
                "status": "not_answered",
                "user_answer": null
            },
            {
                "id": 27,
                "type": "true_false",
                "question_text": "باريس هي عاصمة فرنسا",
                "attached_path": null,
                "order_index": 3,
                "status": "answered",
                "user_answer": {
                    "is_correct": true,
                    "score": 1.0,
                    "answered_at": "2024-01-15T10:30:30.000000Z"
                }
            }
        ],
        "progress": {
            "answered": 1,
            "total": 5,
            "percentage": 20.0
        }
    }
}
```

**حالات الحالة (status):**
- `not_answered`: لم يتم الإجابة عليه بعد
- `answered`: تم الإجابة عليه
- `current`: السؤال الحالي (المحاولة متوقفة عنده)

**ملاحظات:**
- الأسئلة مرتبة حسب `order_index`
- `user_answer` يكون `null` إذا لم يتم الإجابة
- `progress` يعطي معلومات عن التقدم

---

### 3. جلب السؤال الحالي بالتفاصيل

**Endpoint:** `GET /api/v1/user/lessons/{lessonId}/attempts/{attemptId}/current-question`

**Headers:**
```
Authorization: Bearer {token}
```

**Parameters:**
- `lessonId` (path): معرف الدرس
- `attemptId` (path): معرف المحاولة

**Response (200 OK) - قبل الإجابة:**
```json
{
    "success": true,
    "data": {
        "question": {
            "id": 25,
            "type": "single_choice",
            "question_text": "ما هي عاصمة فرنسا؟",
            "attached_path": null,
            "explanation": null,
            "score": 1.0,
            "order_index": 1,
            "options": [
                {
                    "id": 101,
                    "option_text": "لندن",
                    "pair_key": null,
                    "attached_path": null,
                    "order_index": 1
                    // لا يوجد is_correct هنا
                },
                {
                    "id": 102,
                    "option_text": "باريس",
                    "pair_key": null,
                    "attached_path": null,
                    "order_index": 2
                    // لا يوجد is_correct هنا
                },
                {
                    "id": 103,
                    "option_text": "برلين",
                    "pair_key": null,
                    "attached_path": null,
                    "order_index": 3
                },
                {
                    "id": 104,
                    "option_text": "مدريد",
                    "pair_key": null,
                    "attached_path": null,
                    "order_index": 4
                }
            ]
        },
        "user_answer": null
    }
}
```

**Response (200 OK) - بعد الإجابة:**
```json
{
    "success": true,
    "data": {
        "question": {
            "id": 25,
            "type": "single_choice",
            "question_text": "ما هي عاصمة فرنسا؟",
            "attached_path": null,
            "explanation": "باريس هي عاصمة فرنسا وأكبر مدنها",
            "score": 1.0,
            "order_index": 1,
            "options": [
                {
                    "id": 101,
                    "option_text": "لندن",
                    "pair_key": null,
                    "attached_path": null,
                    "order_index": 1,
                    "is_correct": false  // الآن يظهر
                },
                {
                    "id": 102,
                    "option_text": "باريس",
                    "pair_key": null,
                    "attached_path": null,
                    "order_index": 2,
                    "is_correct": true  // الآن يظهر
                }
            ]
        },
        "user_answer": {
            "is_correct": true,
            "score": 1.0,
            "answered_at": "2024-01-15T10:30:30.000000Z",
            "options": [
                {
                    "option_id": 102,
                    "is_correct": true
                }
            ],
            "matches": null
        }
    }
}
```

**حالات الخطأ:**
- `404`: المحاولة غير موجودة
- `400`: المحاولة منتهية بالفعل
- `401`: غير مصرح

**ملاحظات:**
- قبل الإجابة: `is_correct` مخفي، `explanation` = null
- بعد الإجابة: `is_correct` يظهر، `explanation` يظهر
- `user_answer` يحتوي على تفاصيل الإجابة

---

### 4. إرسال الإجابة

**Endpoint:** `POST /api/v1/user/lessons/{lessonId}/attempts/{attemptId}/submit-answer`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body حسب نوع السؤال:**

#### أ) Single Choice / True/False
```json
{
    "question_id": 25,
    "option_id": 102
}
```

#### ب) Multiple Choice
```json
{
    "question_id": 26,
    "option_ids": [105, 107]
}
```

#### ج) Match
```json
{
    "question_id": 30,
    "matches": [
        {
            "left_option_id": 201,
            "right_option_id": 203
        },
        {
            "left_option_id": 202,
            "right_option_id": 204
        }
    ]
}
```

**Response (200 OK):**
```json
{
    "success": true,
    "message": "messages.answer.submitted",
    "data": {
        "is_correct": true,
        "score": 1.0,
        "explanation": "باريس هي عاصمة فرنسا وأكبر مدنها",
        "next_question_id": 26,
        "attempt_finished": false
    }
}
```

**Response عند انتهاء الأسئلة:**
```json
{
    "success": true,
    "message": "messages.answer.submitted",
    "data": {
        "is_correct": true,
        "score": 1.0,
        "explanation": "...",
        "next_question_id": null,
        "attempt_finished": true
    }
}
```

**حالات الخطأ:**
- `400`: السؤال تم الإجابة عليه مسبقاً
- `400`: هذا ليس السؤال الحالي
- `400`: بيانات الإجابة غير صحيحة
- `404`: المحاولة أو السؤال غير موجود
- `401`: غير مصرح

**ملاحظات:**
- بعد الإجابة، يتم تحديث `current_question_id` تلقائياً
- إذا كان آخر سؤال، `attempt_finished` = true
- `next_question_id` يشير إلى السؤال التالي (أو null)

---

### 5. إنهاء المحاولة

**Endpoint:** `POST /api/v1/user/lessons/{lessonId}/attempts/{attemptId}/finish`

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
    "success": true,
    "message": "messages.attempt.finished",
    "data": {
        "final_score": 85.5,
        "total_questions": 5,
        "correct_answers": 4,
        "finished_at": "2024-01-15T10:45:00.000000Z"
    }
}
```

**حالات الخطأ:**
- `404`: المحاولة غير موجودة
- `400`: المحاولة منتهية بالفعل
- `401`: غير مصرح

**ملاحظات:**
- يمكن إنهاء المحاولة يدوياً أو تلقائياً عند انتهاء الأسئلة
- `final_score` هي النسبة المئوية (0-100)

---

## أنواع الأسئلة

### 1. Single Choice (اختيار واحد)

**البنية:**
- سؤال واحد
- عدة خيارات (عادة 4)
- خيار واحد فقط صحيح

**مثال في قاعدة البيانات:**
```
Question:
  id: 25
  type: 'single_choice'
  question_text: 'ما هي عاصمة فرنسا؟'

Options:
  id: 101, option_text: 'لندن', is_correct: 0
  id: 102, option_text: 'باريس', is_correct: 1  ← الصحيح
  id: 103, option_text: 'برلين', is_correct: 0
  id: 104, option_text: 'مدريد', is_correct: 0
```

**Request Body:**
```json
{
    "question_id": 25,
    "option_id": 102
}
```

**التقييم:**
- إذا `option_id` المختار له `is_correct = 1` → الإجابة صحيحة
- النتيجة = `question.score` إذا صحيح، 0 إذا خطأ

---

### 2. Multiple Choice (اختيار متعدد)

**البنية:**
- سؤال واحد
- عدة خيارات
- أكثر من خيار صحيح

**مثال في قاعدة البيانات:**
```
Question:
  id: 26
  type: 'multiple_choice'
  question_text: 'اختر المدن الأوروبية:'

Options:
  id: 105, option_text: 'باريس', is_correct: 1  ← صحيح
  id: 106, option_text: 'القاهرة', is_correct: 0
  id: 107, option_text: 'لندن', is_correct: 1  ← صحيح
  id: 108, option_text: 'طوكيو', is_correct: 0
```

**Request Body:**
```json
{
    "question_id": 26,
    "option_ids": [105, 107]
}
```

**التقييم:**
- يجب أن تكون جميع الخيارات المختارة صحيحة
- يجب أن تكون جميع الخيارات الصحيحة مختارة
- إذا تطابق تماماً → الإجابة صحيحة
- النتيجة = `question.score` إذا صحيح، 0 إذا خطأ

**مثال:**
- الصحيح: [105, 107]
- المستخدم اختار: [105, 107] → ✅ صحيح
- المستخدم اختار: [105] → ❌ خطأ (ناقص 107)
- المستخدم اختار: [105, 106] → ❌ خطأ (106 خطأ)

---

### 3. True/False (صح/خطأ)

**البنية:**
- سؤال واحد
- خياران فقط: صح، خطأ
- خيار واحد صحيح

**مثال في قاعدة البيانات:**
```
Question:
  id: 27
  type: 'true_false'
  question_text: 'باريس هي عاصمة فرنسا'

Options:
  id: 109, option_text: 'صح', is_correct: 1  ← الصحيح
  id: 110, option_text: 'خطأ', is_correct: 0
```

**Request Body:**
```json
{
    "question_id": 27,
    "option_id": 109
}
```

**التقييم:**
- مثل Single Choice تماماً
- النتيجة = `question.score` إذا صحيح، 0 إذا خطأ

---

### 4. Match (مطابقة)

**البنية:**
- سؤال واحد
- خيارات يسارية (مصطلحات)
- خيارات يمينية (تعريفات)
- كل خيار يساري له `pair_key`
- الخيار اليميني الصحيح له نفس `pair_key`

**مثال في قاعدة البيانات:**
```
Question:
  id: 30
  type: 'match'
  question_text: 'طابق المصطلحات مع التعريفات:'

Options:
  id: 201, option_text: 'مصطلح 1', pair_key: 'L1', is_correct: NULL
  id: 202, option_text: 'مصطلح 2', pair_key: 'L2', is_correct: NULL
  id: 203, option_text: 'تعريف 1', pair_key: 'L1', is_correct: NULL  ← يطابق 201
  id: 204, option_text: 'تعريف 2', pair_key: 'L2', is_correct: NULL  ← يطابق 202
```

**Request Body:**
```json
{
    "question_id": 30,
    "matches": [
        {
            "left_option_id": 201,
            "right_option_id": 203
        },
        {
            "left_option_id": 202,
            "right_option_id": 204
        }
    ]
}
```

**التقييم:**
- لكل match: إذا `pair_key` للخيار الأيسر = `pair_key` للخيار الأيمن → صحيح
- يجب أن تكون جميع المطابقات صحيحة
- النتيجة = (عدد المطابقات الصحيحة / إجمالي المطابقات) × `question.score`

**مثال:**
- الصحيح: 201→203, 202→204
- المستخدم: 201→203, 202→204 → ✅ 100% (2/2)
- المستخدم: 201→204, 202→203 → ❌ 0% (0/2)
- المستخدم: 201→203, 202→203 → ❌ 50% (1/2)

---

## أمثلة عملية كاملة

### المثال 1: محاولة كاملة من البداية للنهاية

#### الخطوة 1: المستخدم يفتح الدرس
```http
POST /api/v1/user/lessons/10/start-attempt
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "current_question_id": 25,
        "status": "in_progress"
    }
}
```

**الموبايل يحفظ:** `attempt_id = 1`

---

#### الخطوة 2: جلب جميع الأسئلة لعرض قائمة
```http
GET /api/v1/user/lessons/10/questions?attempt_id=1
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "questions": [
            {"id": 25, "status": "current", "order_index": 1},
            {"id": 26, "status": "not_answered", "order_index": 2},
            {"id": 27, "status": "not_answered", "order_index": 3}
        ],
        "progress": {
            "answered": 0,
            "total": 3,
            "percentage": 0
        }
    }
}
```

**الموبايل يعرض:** قائمة الأسئلة مع إبراز السؤال الحالي

---

#### الخطوة 3: جلب السؤال الحالي
```http
GET /api/v1/user/lessons/10/attempts/1/current-question
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "question": {
            "id": 25,
            "type": "single_choice",
            "question_text": "ما هي عاصمة فرنسا؟",
            "options": [
                {"id": 101, "option_text": "لندن"},
                {"id": 102, "option_text": "باريس"},
                {"id": 103, "option_text": "برلين"},
                {"id": 104, "option_text": "مدريد"}
            ]
        },
        "user_answer": null
    }
}
```

**الموبايل يعرض:** السؤال مع الخيارات (بدون is_correct)

---

#### الخطوة 4: المستخدم يختار إجابة
```http
POST /api/v1/user/lessons/10/attempts/1/submit-answer
Authorization: Bearer {token}
Content-Type: application/json

{
    "question_id": 25,
    "option_id": 102
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "is_correct": true,
        "score": 1.0,
        "explanation": "باريس هي عاصمة فرنسا وأكبر مدنها",
        "next_question_id": 26,
        "attempt_finished": false
    }
}
```

**الموبايل:**
1. يعرض النتيجة (صحيح/خطأ)
2. يعرض الشرح
3. ينتقل تلقائياً للسؤال التالي (26)

---

#### الخطوة 5: جلب السؤال التالي
```http
GET /api/v1/user/lessons/10/attempts/1/current-question
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "question": {
            "id": 26,
            "type": "multiple_choice",
            "question_text": "اختر المدن الأوروبية:",
            "options": [
                {"id": 105, "option_text": "باريس"},
                {"id": 106, "option_text": "القاهرة"},
                {"id": 107, "option_text": "لندن"},
                {"id": 108, "option_text": "طوكيو"}
            ]
        },
        "user_answer": null
    }
}
```

**الموبايل يعرض:** السؤال الثاني

---

#### الخطوة 6: إرسال إجابة السؤال الثاني
```http
POST /api/v1/user/lessons/10/attempts/1/submit-answer
Authorization: Bearer {token}
Content-Type: application/json

{
    "question_id": 26,
    "option_ids": [105, 107]
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "is_correct": true,
        "score": 1.0,
        "explanation": "...",
        "next_question_id": 27,
        "attempt_finished": false
    }
}
```

---

#### الخطوة 7: إرسال إجابة السؤال الأخير
```http
POST /api/v1/user/lessons/10/attempts/1/submit-answer
Authorization: Bearer {token}
Content-Type: application/json

{
    "question_id": 27,
    "option_id": 109
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "is_correct": true,
        "score": 1.0,
        "explanation": "...",
        "next_question_id": null,
        "attempt_finished": true
    }
}
```

**الموبايل:**
- يعرض النتيجة النهائية
- يعرض الإحصائيات

---

#### الخطوة 8 (اختياري): إنهاء المحاولة يدوياً
```http
POST /api/v1/user/lessons/10/attempts/1/finish
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "final_score": 100.0,
        "total_questions": 3,
        "correct_answers": 3,
        "finished_at": "2024-01-15T10:45:00.000000Z"
    }
}
```

---

### المثال 2: إعادة المحاولة

#### السيناريو: المستخدم يريد إعادة المحاولة

**الخطوة 1: بدء محاولة جديدة**
```http
POST /api/v1/user/lessons/10/start-attempt
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 2,  // محاولة جديدة
        "current_question_id": 25,
        "status": "in_progress"
    }
}
```

**ملاحظات:**
- يتم إنشاء محاولة جديدة (id: 2)
- المحاولة السابقة (id: 1) تبقى محفوظة للتاريخ
- `current_question_id` يعود لأول سؤال

---

### المثال 3: استئناف محاولة متوقفة

#### السيناريو: المستخدم أغلق التطبيق وعاد لاحقاً

**الخطوة 1: بدء المحاولة (ستجد المحاولة القائمة)**
```http
POST /api/v1/user/lessons/10/start-attempt
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,  // نفس المحاولة
        "current_question_id": 26,  // السؤال الذي توقف عنده
        "status": "in_progress"
    }
}
```

**الخطوة 2: جلب الأسئلة لمعرفة التقدم**
```http
GET /api/v1/user/lessons/10/questions?attempt_id=1
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "questions": [
            {"id": 25, "status": "answered"},
            {"id": 26, "status": "current"},
            {"id": 27, "status": "not_answered"}
        ],
        "progress": {
            "answered": 1,
            "total": 3,
            "percentage": 33.33
        }
    }
}
```

**الموبايل يعرض:** التقدم السابق ويستأنف من السؤال الحالي

---

## حالات خاصة ومعالجة الأخطاء

### 1. محاولة الإجابة على سؤال تم الإجابة عليه مسبقاً

**Request:**
```http
POST /api/v1/user/lessons/10/attempts/1/submit-answer
{
    "question_id": 25,  // تم الإجابة عليه مسبقاً
    "option_id": 102
}
```

**Response (400 Bad Request):**
```json
{
    "success": false,
    "message": "messages.question.already_answered"
}
```

**الحل:** لا يمكن الإجابة على سؤال مرتين في نفس المحاولة. يجب بدء محاولة جديدة.

---

### 2. محاولة الإجابة على سؤال ليس الحالي

**Request:**
```http
POST /api/v1/user/lessons/10/attempts/1/submit-answer
{
    "question_id": 27,  // السؤال الحالي هو 26
    "option_id": 109
}
```

**Response (400 Bad Request):**
```json
{
    "success": false,
    "message": "messages.question.not_current"
}
```

**الحل:** يجب الإجابة على الأسئلة بالترتيب. السؤال الحالي هو 26.

---

### 3. محاولة إنهاء محاولة منتهية

**Request:**
```http
POST /api/v1/user/lessons/10/attempts/1/finish
```

**Response (400 Bad Request):**
```json
{
    "success": false,
    "message": "messages.attempt.already_finished"
}
```

**الحل:** المحاولة منتهية بالفعل. ابدأ محاولة جديدة.

---

### 4. بيانات إجابة غير صحيحة

**Request (Multiple Choice بدون option_ids):**
```http
POST /api/v1/user/lessons/10/attempts/1/submit-answer
{
    "question_id": 26,
    "option_id": 105  // خطأ: يجب أن يكون option_ids (مصفوفة)
}
```

**Response (400 Bad Request):**
```json
{
    "success": false,
    "message": "messages.answer.option_ids_required"
}
```

**الحل:** تأكد من إرسال البيانات الصحيحة حسب نوع السؤال.

---

### 5. خيار غير موجود

**Request:**
```http
POST /api/v1/user/lessons/10/attempts/1/submit-answer
{
    "question_id": 25,
    "option_id": 999  // غير موجود
}
```

**Response (400 Bad Request):**
```json
{
    "success": false,
    "message": "messages.answer.invalid_option"
}
```

**الحل:** تأكد من أن `option_id` ينتمي للسؤال.

---

## نصائح للتنفيذ في الموبايل

### 1. إدارة الحالة (State Management)

```dart
// مثال Flutter
class LessonAttemptState {
  int? attemptId;
  int? currentQuestionId;
  List<QuestionWithStatus> questions = [];
  Map<int, QuestionDetail> questionDetails = {};
  ProgressInfo? progress;
  
  bool get isFinished => attemptId != null && progress?.percentage == 100;
}
```

### 2. التخزين المحلي (Local Storage)

- احفظ `attempt_id` محلياً عند بدء المحاولة
- عند فتح التطبيق، استخدم `attempt_id` المحفوظ
- احذف `attempt_id` عند انتهاء المحاولة

### 3. معالجة الأخطاء

```dart
try {
  final response = await submitAnswer(questionId, answerData);
  if (response.data['attempt_finished']) {
    // عرض النتيجة النهائية
    showFinalResults(response.data);
  } else {
    // الانتقال للسؤال التالي
    loadNextQuestion(response.data['next_question_id']);
  }
} catch (e) {
  if (e.message == 'messages.question.already_answered') {
    // عرض رسالة: تم الإجابة على هذا السؤال مسبقاً
  } else if (e.message == 'messages.question.not_current') {
    // عرض رسالة: يجب الإجابة على الأسئلة بالترتيب
  }
}
```

### 4. تحديث UI بعد الإجابة

```dart
// بعد الإجابة بنجاح:
1. تحديث حالة السؤال في القائمة (answered)
2. تحديث progress
3. إظهار النتيجة (صحيح/خطأ)
4. إظهار الشرح
5. الانتقال للسؤال التالي بعد 2-3 ثواني
```

### 5. عرض التقدم

```dart
// Progress Bar
LinearProgressIndicator(
  value: progress.percentage / 100,
  label: '${progress.answered}/${progress.total}',
)

// قائمة الأسئلة
ListView.builder(
  itemBuilder: (context, index) {
    final question = questions[index];
    return QuestionListItem(
      question: question,
      status: question.status, // current, answered, not_answered
      onTap: () {
        if (question.status == 'current' || question.status == 'answered') {
          loadQuestion(question.id);
        }
      },
    );
  },
)
```

---

## ملخص سريع

### Flow الأساسي:
1. `POST /lessons/{id}/start-attempt` → الحصول على `attempt_id`
2. `GET /lessons/{id}/questions?attempt_id={id}` → جلب جميع الأسئلة مع الحالات
3. `GET /lessons/{id}/attempts/{id}/current-question` → جلب السؤال الحالي
4. `POST /lessons/{id}/attempts/{id}/submit-answer` → إرسال الإجابة
5. (اختياري) `POST /lessons/{id}/attempts/{id}/finish` → إنهاء المحاولة

### أنواع الأسئلة:
- **single_choice**: `option_id` (واحد)
- **multiple_choice**: `option_ids` (مصفوفة)
- **true_false**: `option_id` (واحد)
- **match**: `matches` (مصفوفة من {left_option_id, right_option_id})

### حالات الأسئلة:
- `not_answered`: لم يُجاب
- `answered`: تم الإجابة
- `current`: السؤال الحالي

### الأمان:
- جميع الـ APIs تتطلب `Authorization: Bearer {token}`
- التحقق من ملكية المستخدم للمحاولة
- منع الإجابة على سؤال مرتين
- منع الإجابة على سؤال ليس الحالي

---

## أسئلة شائعة

**س: هل يمكن الإجابة على سؤال مرتين؟**
ج: لا، في نفس المحاولة. يجب بدء محاولة جديدة.

**س: هل يجب الإجابة على الأسئلة بالترتيب؟**
ج: نعم، يجب الإجابة على السؤال الحالي فقط.

**س: ماذا يحدث إذا أغلق المستخدم التطبيق؟**
ج: المحاولة تبقى محفوظة. عند العودة، استخدم `start-attempt` وستجد المحاولة القائمة.

**س: كيف أعرف أن المحاولة انتهت؟**
ج: `attempt_finished = true` في response، أو `status = 'finished'` في المحاولة.

**س: كيف أحسب النتيجة النهائية؟**
ج: `final_score` في response عند إنهاء المحاولة، أو `score` في المحاولة.

---

**آخر تحديث:** 2024-01-15

