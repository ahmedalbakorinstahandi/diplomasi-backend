# دليل مطور الداش بورد لإدارة السيناريوهات (الإصدار الحالي)

## الهدف

هذا الدليل يشرح بشكل عملي كيفية بناء واجهات الداش بورد للتعامل مع السيناريوهات بعد التحديثات الأخيرة، مع الحفاظ الكامل على البنية الحالية:

- `Scenario -> ScenarioQuestion -> ScenarioQuestionOption`
- نفس الربط مع `Level` و `LevelTrack`
- نفس دورة تشغيل السيناريو للمستخدم

---

## ما الجديد في هذا الإصدار

1. إضافة `feedback_text` لكل خيار (اختياري) لعرض رسالة بعد الاختيار.
2. اعتماد `single_choice` فقط في سيناريوهات التفاوض.
3. دعم الربط السهل عبر `next_question_code` (بالإضافة إلى `next_question_id`).
4. إدارة تلقائية وآمنة لـ `start_question_id` عند إنشاء/حذف الأسئلة.
5. فحص تدفق أقوى مع endpoint مخصص: `validate-flow`.

---

## نموذج البيانات

## 1) Scenario

- يمثل الحلقة.
- أهم الحقول:
  - `id`
  - `level_id`
  - `title`
  - `description`
  - `start_question_id`
  - `is_published`
  - `order_index`

## 2) ScenarioQuestion

- يمثل الشاشة داخل الحلقة.
- أهم الحقول:
  - `id`
  - `scenario_id`
  - `code` (مثل: `S1`, `S2`, أو `1`, `2`)
  - `type` (الآن: `single_choice`)
  - `question_text`
  - `explanation` (رسالة عامة اختيارية)
  - `order_index`

## 3) ScenarioQuestionOption

- يمثل اختيار المستخدم داخل الشاشة.
- أهم الحقول:
  - `id`
  - `question_id`
  - `option_text`
  - `feedback_text` (رسالة بعد الاختيار)
  - `next_question_id` (انتقال مباشر)
  - `next_question_code` (للإنشاء/التحديث فقط، يتحول داخلياً إلى `next_question_id`)
  - `order_index`

---

## دورة بناء سيناريو في الداش بورد (عملي)

1. أنشئ السيناريو (`Scenario`) مع عنوان ووصف.
2. أنشئ الشاشات (`ScenarioQuestion`) مع `code` واضح.
3. أضف خيارات كل شاشة (`ScenarioQuestionOption`) مع:
   - نص الاختيار
   - `feedback_text` اختياري
   - وجهة الانتقال (`next_question_id` أو `next_question_code`)
4. اختبر التدفق عبر endpoint الفحص.
5. بعد نجاح الفحص، قم بالنشر (`is_published = true`).

---

## API للإدارة (Admin)

المسار الأساسي: `/api/v1/admin`

## السيناريوهات

- `GET /scenarios`
- `GET /scenarios/{id}`
- `POST /scenarios`
- `PUT /scenarios/{id}`
- `DELETE /scenarios/{id}`

## أسئلة السيناريو

- `GET /scenario-questions`
- `GET /scenario-questions/{id}`
- `POST /scenario-questions`
- `PUT /scenario-questions/{id}`
- `DELETE /scenario-questions/{id}`
- `GET /scenario-questions/validate-flow/check?scenario_id={id}&strict=true`

---

## أمثلة Requests/Responses

## 1) إنشاء سؤال مع خيارات وربط بالكود

```json
POST /api/v1/admin/scenario-questions
{
  "scenario_id": 10,
  "code": "2",
  "type": "single_choice",
  "question_text": "كيف تتصرف الآن؟",
  "explanation": "تغذية عامة تظهر إذا لم يوجد feedback خاص بالخيار",
  "options": [
    {
      "option_text": "أعتذر وأعيد صياغة الحديث",
      "feedback_text": "محاولة جيدة، أنقذت الموقف.",
      "next_question_code": "3"
    },
    {
      "option_text": "أصر على موقفي",
      "feedback_text": "هذا يزيد التوتر ويقود للفشل.",
      "next_question_code": "5"
    }
  ]
}
```

## 2) Response خيار ضمن السؤال

```json
{
  "id": 501,
  "question_id": 120,
  "option_text": "أعتذر وأعيد صياغة الحديث",
  "feedback_text": "محاولة جيدة، أنقذت الموقف.",
  "next_question_id": 121,
  "next_question_code": "3",
  "attached_path": null,
  "order_index": 1
}
```

## 3) فحص التدفق قبل النشر

```json
GET /api/v1/admin/scenario-questions/validate-flow/check?scenario_id=10&strict=true
```

```json
{
  "success": true,
  "data": {
    "success": true,
    "message": null,
    "details": {
      "unreachable_question_ids": [],
      "has_terminal_path": true
    }
  }
}
```

---

## تشغيل السيناريو في تطبيق الموبايل (User Runtime)

1. بدء محاولة:
   - `POST /api/v1/user/scenarios/{id}/start-attempt`
2. جلب الشاشة الحالية:
   - `GET /api/v1/user/scenarios/{id}/attempts/{attemptId}/current-question`
3. إرسال اختيار المستخدم:
   - `POST /api/v1/user/scenarios/submit-answer`
4. الاستجابة الآن تتضمن:
   - `feedback_text` (من الخيار المختار)
   - `next_question_id`
   - `finished`
   - `explanation` (عام)

اقتراح واجهة الموبايل:
- اعرض `feedback_text` أولاً إن وجد.
- إذا لم يوجد، استخدم `explanation`.
- بعد ضغط \"التالي\" انتقل للشاشة القادمة.

---

## قواعد التحقق المهمة

عند الفحص الصارم (`strict=true`) النظام يتحقق من:

1. وجود `start_question_id` صالح.
2. كل `next_question_id` ينتمي لنفس السيناريو.
3. عدم وجود `deadlock` (كل الخيارات تعود لنفس السؤال فقط).
4. وجود مسار نهائي قابل للوصول (`terminal path`).
5. عدم وجود أسئلة غير قابلة للوصول من البداية.

---

## إدارة start_question_id تلقائياً

1. عند إنشاء أول سؤال داخل سيناريو:
   - يتم تعيينه تلقائياً كبداية.
2. عند حذف سؤال البداية:
   - يتم اختيار بديل تلقائياً (حسب `order_index` ثم `id`).
3. إذا لم تبق أسئلة:
   - تصبح البداية `null`.
   - يمنع نشر السيناريو حتى إعادة تعيين بداية صالحة.

---

## ملاحظات تنفيذ للداش بورد

1. اعتمد `code` كمعرف بشري للربط.
2. عند الحفظ، يمكنك إرسال:
   - `next_question_id` مباشرة، أو
   - `next_question_code` لتسهيل تجربة المؤلف.
3. أضف زر \"فحص التدفق\" قبل زر \"نشر\".
4. عند ظهور خطأ تدفق:
   - اعرض نوعه بوضوح (Deadlock / Unreachable / No Terminal Path / Invalid Target).

---

## سيناريو كامل من البداية للنهاية

1. شاشة 1: 3 خيارات.
2. خيار (أ) -> شاشة 2 مع `feedback_text` تصحيحي.
3. خيار (ب) -> شاشة 3 مع `feedback_text` إيجابي.
4. شاشة 3 -> شاشة 6.
5. شاشة 6:
   - خيار (أ) -> شاشة 7 (نهاية ناجحة: `next_question_id = null` بعد رسالة نهاية).
   - خيار (ب) -> شاشة 8 (نهاية متوسطة).
6. شاشة 5 (فشل) تحتوي خيار \"إعادة المحاولة\" يرجع إلى `start_question_id`.

---

## الأخطاء الشائعة وكيف يتعامل معها الداش بورد

1. `next_question_code` غير موجود:
   - صحّح `code` الهدف أو أنشئ الشاشة أولاً.
2. لا يوجد `start_question_id`:
   - أضف شاشة بداية أو عدل البداية يدوياً.
3. لا يوجد مسار نهائي:
   - اجعل خياراً واحداً على الأقل ينتهي بـ `next_question_id = null`.
4. أسئلة غير قابلة للوصول:
   - اربطها من شاشة قابلة للوصول أو احذفها.

---

## توصية تشغيل

لأفضل استقرار:

1. استخدم `strict=true` قبل كل نشر.
2. انشر السيناريو فقط بعد نجاح الفحص.
3. على الموبايل، لا تتعامل مع الخيارات كـ \"صح/خطأ\" بل كـ \"مسارات\".

