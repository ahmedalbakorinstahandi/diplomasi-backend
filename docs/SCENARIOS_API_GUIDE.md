# دليل API السيناريوهات (Scenarios API Guide)

## نظرة عامة

نظام السيناريوهات هو نظام تفاعلي يتيح للمستخدمين التنقل بين الأسئلة بناءً على إجاباتهم. كل سؤال يحتوي على خيارات، وكل خيار يحدد السؤال التالي الذي سيظهر للمستخدم.

## البنية الأساسية

### 1. **Scenario (السيناريو)**
- يمثل سيناريو كامل يحتوي على مجموعة من الأسئلة المتسلسلة
- يحتوي على `start_question_id` الذي يشير إلى السؤال الأول في السيناريو
- كل سيناريو مرتبط بـ `level` (مستوى)

### 2. **ScenarioQuestion (سؤال السيناريو)**
- يمثل سؤال واحد في السيناريو
- له نوعان: `single_choice` أو `true_false`
- يحتوي على `question_text` (نص السؤال) و `explanation` (شرح بعد الإجابة)
- يمكن أن يحتوي على `attached_path` (مرفق مثل صورة أو فيديو)

### 3. **ScenarioQuestionOption (خيار السؤال)**
- يمثل خيار إجابة واحد للسؤال
- يحتوي على `option_text` (نص الخيار)
- **الأهم**: يحتوي على `next_question_id` الذي يحدد السؤال التالي عند اختيار هذا الخيار
- إذا كان `next_question_id` = `null`، فهذا يعني نهاية السيناريو

### 4. **UserScenarioAttempt (محاولة المستخدم)**
- يمثل محاولة المستخدم لإكمال سيناريو
- الحالات: `in_progress`, `finished`, `abandoned`
- يحتوي على `started_at` و `finished_at`

### 5. **UserScenarioQuestionAnswer (إجابة المستخدم)**
- يمثل إجابة المستخدم على سؤال معين
- يحتوي على `step_index` (رقم الخطوة في السيناريو)
- يحتوي على `answered_at` و `time_spent`

### 6. **UserScenarioAnswerOption (الخيار المختار)**
- يربط بين الإجابة والخيار الذي اختاره المستخدم
- يتم حفظه في جدول منفصل

## دورة حياة السيناريو

### المرحلة 1: بدء السيناريو
1. المستخدم يختار سيناريو
2. يتم استدعاء `POST /api/v1/user/scenarios/{id}/start-attempt`
3. النظام يتحقق من وجود محاولة قائمة (`in_progress`)
   - إذا كانت موجودة: يتم إرجاعها
   - إذا لم تكن موجودة: يتم إنشاء محاولة جديدة
4. يتم إرجاع `attempt_id` و `scenario_id`

### المرحلة 2: جلب السؤال الحالي
1. يتم استدعاء `GET /api/v1/user/scenarios/{id}/attempts/{attemptId}/current-question`
2. النظام يحدد السؤال الحالي:
   - إذا لم تكن هناك إجابات: يعيد `start_question_id` من السيناريو
   - إذا كانت هناك إجابات: يأخذ آخر إجابة، ثم يأخذ `next_question_id` من الخيار المختار
3. إذا كان `next_question_id` = `null`: السيناريو انتهى
4. يتم إرجاع السؤال مع جميع خياراته

### المرحلة 3: إرسال الإجابة
1. المستخدم يختار خياراً
2. يتم استدعاء `POST /api/v1/user/scenarios/submit-answer`
3. النظام:
   - يحفظ الإجابة في `user_scenario_question_answers`
   - يحفظ الخيار المختار في `user_scenario_answer_options`
   - يحدد `next_question_id` من الخيار المختار
   - إذا كان `next_question_id` = `null`: ينهي المحاولة تلقائياً
4. يتم إرجاع:
   - `next_question_id`: السؤال التالي (أو `null` إذا انتهى)
   - `finished`: `true` إذا انتهى السيناريو
   - `explanation`: شرح السؤال (إن وجد)

### المرحلة 4: الانتقال للسؤال التالي
1. بعد إرسال الإجابة، يتم استدعاء `getCurrentQuestion` مرة أخرى
2. النظام يعيد السؤال الجديد بناءً على `next_question_id`
3. تتكرر العملية حتى ينتهي السيناريو

### المرحلة 5: إنهاء السيناريو (اختياري)
- يمكن إنهاء السيناريو يدوياً باستدعاء `POST /api/v1/user/scenarios/{id}/attempts/{attemptId}/finish`
- أو يتم إنهاؤه تلقائياً عند الوصول لسؤال بدون `next_question_id`

## الـ Endpoints

### 1. بدء محاولة جديدة
```http
POST /api/v1/user/scenarios/{id}/start-attempt
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 5,
    "scenario_id": 3,
    "status": "in_progress",
    "started_at": "2024-01-15T10:30:00.000000Z",
    "finished_at": null
  },
  "message": "messages.scenario.attempt_started",
  "status": 201
}
```

**ملاحظات:**
- إذا كانت هناك محاولة قائمة (`in_progress`)، يتم إرجاعها بدلاً من إنشاء محاولة جديدة
- يجب أن يكون السيناريو لديه `start_question_id`

---

### 2. جلب السؤال الحالي
```http
GET /api/v1/user/scenarios/{id}/attempts/{attemptId}/current-question
Authorization: Bearer {token}
```

**Response (سؤال جديد):**
```json
{
  "success": true,
  "data": {
    "question": {
      "id": 10,
      "scenario_id": 3,
      "code": "Q1",
      "type": "single_choice",
      "question_text": "ما هو قرارك الأول؟",
      "attached_path": null,
      "explanation": null,
      "order_index": 1,
      "scenario_question_options": [
        {
          "id": 25,
          "question_id": 10,
          "option_text": "الخيار الأول",
          "next_question_id": 11,
          "attached_path": null,
          "order_index": 1
        },
        {
          "id": 26,
          "question_id": 10,
          "option_text": "الخيار الثاني",
          "next_question_id": 12,
          "attached_path": null,
          "order_index": 2
        }
      ]
    },
    "answered": false
  },
  "status": 200
}
```

**Response (سيناريو منتهي):**
```json
{
  "success": true,
  "data": {
    "finished": true,
    "message": "messages.scenario.finished"
  },
  "status": 200
}
```

**Response (سؤال تم الإجابة عليه مسبقاً):**
```json
{
  "success": true,
  "data": {
    "question": {
      "id": 10,
      "scenario_id": 3,
      "code": "Q1",
      "type": "single_choice",
      "question_text": "ما هو قرارك الأول؟",
      "scenario_question_options": [...]
    },
    "answered": true,
    "answer": {
      "id": 5,
      "question_id": 10,
      "attempt_id": 1,
      "step_index": 1,
      "answered_at": "2024-01-15T10:35:00.000000Z",
      "user_scenario_answer_options": [
        {
          "id": 8,
          "user_answer_id": 5,
          "option_id": 25,
          "scenario_question_option": {
            "id": 25,
            "option_text": "الخيار الأول",
            "next_question_id": 11
          }
        }
      ]
    }
  },
  "status": 200
}
```

**ملاحظات:**
- إذا كان `answered: true`، يعني أن المستخدم أجاب على هذا السؤال مسبقاً
- يمكن استخدام هذا للسماح للمستخدم بالعودة للسؤال السابق

---

### 3. إرسال إجابة
```http
POST /api/v1/user/scenarios/submit-answer
Authorization: Bearer {token}
Content-Type: application/json

{
  "attempt_id": 1,
  "question_id": 10,
  "option_id": 25
}
```

**ملاحظات:**
- `option_id` مطلوب للأسئلة من نوع `single_choice` و `true_false`
- `answer_text` يمكن استخدامه للأسئلة النصية (إن وجدت في المستقبل)

**Response (مع سؤال تالي):**
```json
{
  "success": true,
  "data": {
    "answer": {
      "id": 5,
      "question_id": 10,
      "attempt_id": 1,
      "step_index": 1,
      "answered_at": "2024-01-15T10:35:00.000000Z",
      "time_spent": null,
      "user_scenario_answer_options": [
        {
          "id": 8,
          "user_answer_id": 5,
          "option_id": 25,
          "scenario_question_option": {
            "id": 25,
            "option_text": "الخيار الأول",
            "next_question_id": 11
          }
        }
      ]
    },
    "next_question_id": 11,
    "finished": false,
    "explanation": "شرح السؤال (إن وجد)"
  },
  "message": "messages.scenario.answer_submitted",
  "status": 201
}
```

**Response (سيناريو منتهي):**
```json
{
  "success": true,
  "data": {
    "answer": {
      "id": 5,
      "question_id": 10,
      "attempt_id": 1,
      "step_index": 1,
      "answered_at": "2024-01-15T10:35:00.000000Z",
      "user_scenario_answer_options": [
        {
          "id": 8,
          "user_answer_id": 5,
          "option_id": 25,
          "scenario_question_option": {
            "id": 25,
            "option_text": "الخيار الأول",
            "next_question_id": null
          }
        }
      ]
    },
    "next_question_id": null,
    "finished": true,
    "explanation": "شرح السؤال (إن وجد)"
  },
  "message": "messages.scenario.finished",
  "status": 201
}
```

**ملاحظات:**
- إذا كان `finished: true`، يعني أن السيناريو انتهى
- `next_question_id` = `null` يعني لا يوجد سؤال تالي
- بعد إرسال الإجابة، استدعِ `getCurrentQuestion` للحصول على السؤال التالي

---

### 4. إنهاء المحاولة يدوياً
```http
POST /api/v1/user/scenarios/{id}/attempts/{attemptId}/finish
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 5,
    "scenario_id": 3,
    "status": "finished",
    "started_at": "2024-01-15T10:30:00.000000Z",
    "finished_at": "2024-01-15T10:45:00.000000Z"
  },
  "message": "messages.scenario.attempt_finished",
  "status": 200
}
```

---

## سير العمل الكامل (Flow) في Flutter

### 1. بدء السيناريو
```dart
// 1. المستخدم يختار سيناريو
final scenarioId = 3;

// 2. بدء المحاولة
final response = await http.post(
  Uri.parse('$baseUrl/api/v1/user/scenarios/$scenarioId/start-attempt'),
  headers: {
    'Authorization': 'Bearer $token',
    'Content-Type': 'application/json',
  },
);

final data = jsonDecode(response.body);
final attemptId = data['data']['id'];
```

### 2. جلب السؤال الحالي
```dart
Future<Map<String, dynamic>?> getCurrentQuestion(int scenarioId, int attemptId) async {
  final response = await http.get(
    Uri.parse('$baseUrl/api/v1/user/scenarios/$scenarioId/attempts/$attemptId/current-question'),
    headers: {
      'Authorization': 'Bearer $token',
    },
  );

  final data = jsonDecode(response.body);
  
  if (data['data']['finished'] == true) {
    // السيناريو انتهى
    return null;
  }
  
  return data['data'];
}
```

### 3. عرض السؤال
```dart
void displayQuestion(Map<String, dynamic> questionData) {
  final question = questionData['question'];
  final questionText = question['question_text'];
  final options = question['scenario_question_options'] as List;
  
  // عرض السؤال والخيارات في UI
  showQuestionDialog(
    questionText: questionText,
    options: options.map((opt) => {
      'id': opt['id'],
      'text': opt['option_text'],
    }).toList(),
  );
}
```

### 4. إرسال الإجابة
```dart
Future<Map<String, dynamic>> submitAnswer(
  int attemptId,
  int questionId,
  int optionId,
) async {
  final response = await http.post(
    Uri.parse('$baseUrl/api/v1/user/scenarios/submit-answer'),
    headers: {
      'Authorization': 'Bearer $token',
      'Content-Type': 'application/json',
    },
    body: jsonEncode({
      'attempt_id': attemptId,
      'question_id': questionId,
      'option_id': optionId,
    }),
  );

  final data = jsonDecode(response.body);
  return data['data'];
}
```

### 5. الحلقة الكاملة
```dart
class ScenarioScreen extends StatefulWidget {
  final int scenarioId;
  
  @override
  _ScenarioScreenState createState() => _ScenarioScreenState();
}

class _ScenarioScreenState extends State<ScenarioScreen> {
  int? attemptId;
  Map<String, dynamic>? currentQuestion;
  bool isLoading = false;
  bool isFinished = false;

  @override
  void initState() {
    super.initState();
    startScenario();
  }

  Future<void> startScenario() async {
    setState(() => isLoading = true);
    
    try {
      // 1. بدء المحاولة
      final startResponse = await http.post(
        Uri.parse('$baseUrl/api/v1/user/scenarios/${widget.scenarioId}/start-attempt'),
        headers: {
          'Authorization': 'Bearer $token',
          'Content-Type': 'application/json',
        },
      );
      
      final startData = jsonDecode(startResponse.body);
      attemptId = startData['data']['id'];
      
      // 2. جلب السؤال الأول
      await loadCurrentQuestion();
    } catch (e) {
      // معالجة الخطأ
    } finally {
      setState(() => isLoading = false);
    }
  }

  Future<void> loadCurrentQuestion() async {
    if (attemptId == null) return;
    
    setState(() => isLoading = true);
    
    try {
      final questionData = await getCurrentQuestion(widget.scenarioId, attemptId!);
      
      if (questionData == null) {
        // السيناريو انتهى
        setState(() {
          isFinished = true;
          currentQuestion = null;
        });
      } else {
        setState(() {
          currentQuestion = questionData;
        });
      }
    } catch (e) {
      // معالجة الخطأ
    } finally {
      setState(() => isLoading = false);
    }
  }

  Future<void> handleAnswerSelection(int optionId) async {
    if (currentQuestion == null || attemptId == null) return;
    
    final question = currentQuestion!['question'];
    final questionId = question['id'];
    
    setState(() => isLoading = true);
    
    try {
      // إرسال الإجابة
      final answerData = await submitAnswer(attemptId!, questionId, optionId);
      
      // عرض الشرح إن وجد
      if (answerData['explanation'] != null) {
        showExplanationDialog(answerData['explanation']);
      }
      
      // التحقق من انتهاء السيناريو
      if (answerData['finished'] == true) {
        setState(() {
          isFinished = true;
          currentQuestion = null;
        });
        showCompletionDialog();
      } else {
        // الانتقال للسؤال التالي
        await loadCurrentQuestion();
      }
    } catch (e) {
      // معالجة الخطأ
    } finally {
      setState(() => isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (isLoading) {
      return Center(child: CircularProgressIndicator());
    }
    
    if (isFinished) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.check_circle, size: 64, color: Colors.green),
            SizedBox(height: 16),
            Text('تم إكمال السيناريو بنجاح!'),
          ],
        ),
      );
    }
    
    if (currentQuestion == null) {
      return Center(child: Text('لا يوجد سؤال'));
    }
    
    final question = currentQuestion!['question'];
    final options = question['scenario_question_options'] as List;
    
    return Scaffold(
      appBar: AppBar(title: Text('السيناريو')),
      body: Column(
        children: [
          // عرض السؤال
          Padding(
            padding: EdgeInsets.all(16),
            child: Text(
              question['question_text'],
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
          ),
          
          // عرض الخيارات
          Expanded(
            child: ListView.builder(
              itemCount: options.length,
              itemBuilder: (context, index) {
                final option = options[index];
                return ListTile(
                  title: Text(option['option_text']),
                  onTap: () => handleAnswerSelection(option['id']),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
```

## ملاحظات مهمة

### 1. **تحديد السؤال التالي**
- السؤال التالي يتم تحديده تلقائياً من `next_question_id` في الخيار المختار
- لا تحتاج لتحديد السؤال التالي يدوياً
- إذا كان `next_question_id` = `null`، السيناريو ينتهي تلقائياً

### 2. **إدارة الحالة**
- استخدم `attempt_id` لتتبع المحاولة الحالية
- استخدم `step_index` لمعرفة ترتيب الإجابات
- استخدم `status` في المحاولة لمعرفة إذا كانت `in_progress` أو `finished`

### 3. **معالجة الأخطاء**
- تحقق من `status` في الـ response
- تحقق من `finished` بعد إرسال الإجابة
- تحقق من وجود `next_question_id` قبل الانتقال للسؤال التالي

### 4. **الأداء**
- يمكنك حفظ `attempt_id` محلياً لتجنب إعادة بدء المحاولة
- يمكنك عرض السؤال التالي مباشرة بعد إرسال الإجابة دون الحاجة لاستدعاء `getCurrentQuestion` إذا كنت تعرف `next_question_id`

### 5. **الانتقال للخلف**
- يمكنك استخدام `getCurrentQuestion` مع `attemptId` و `questionId` محدد للعودة لسؤال سابق
- النظام يحفظ جميع الإجابات، لذا يمكنك عرضها في أي وقت

## أمثلة على السيناريوهات

### سيناريو بسيط (3 أسئلة)
```
Q1 (start_question_id)
  ├─ Option 1 → Q2
  └─ Option 2 → Q3

Q2
  ├─ Option 1 → Q3
  └─ Option 2 → null (نهاية)

Q3
  └─ Option 1 → null (نهاية)
```

### سيناريو معقد (شجرة قرارات)
```
Q1 (start_question_id)
  ├─ Option 1 → Q2
  ├─ Option 2 → Q3
  └─ Option 3 → Q4

Q2
  ├─ Option 1 → Q5
  └─ Option 2 → null

Q3
  └─ Option 1 → Q5

Q4
  └─ Option 1 → null

Q5
  └─ Option 1 → null
```

## الخلاصة

1. **ابدأ المحاولة**: `POST /scenarios/{id}/start-attempt`
2. **احصل على السؤال الحالي**: `GET /scenarios/{id}/attempts/{attemptId}/current-question`
3. **أرسل الإجابة**: `POST /scenarios/submit-answer`
4. **كرر الخطوتين 2 و 3** حتى ينتهي السيناريو
5. **أنهِ المحاولة** (اختياري): `POST /scenarios/{id}/attempts/{attemptId}/finish`

النظام يدير تلقائياً:
- تحديد السؤال التالي بناءً على الإجابة
- إنهاء السيناريو عند الوصول لسؤال بدون `next_question_id`
- حفظ جميع الإجابات والخطوات

