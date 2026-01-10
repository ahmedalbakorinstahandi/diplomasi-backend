# دليل API حذف الحساب (Account Deletion API Guide)

## نظرة عامة (Overview)

يقدم هذا الدليل شرحاً مفصلاً لنظام حذف الحساب بخطوتين في تطبيق DiplomaSi. النظام يعمل على مرحلتين لضمان أمان عملية الحذف:

1. **المرحلة الأولى**: طلب حذف الحساب - يتم إرسال رمز حذف إلى البريد الإلكتروني للمستخدم
2. **المرحلة الثانية**: تأكيد حذف الحساب - إدخال الرمز وتنفيذ عملية الحذف النهائية

## المتطلبات الأساسية (Prerequisites)

- المستخدم يجب أن يكون مسجلاً الدخول (authenticated)
- المستخدم يجب أن يكون لديه بريد إلكتروني مسجل
- إعدادات البريد الإلكتروني يجب أن تكون مكونة بشكل صحيح في ملف `.env`

## Endpoints

### 1. طلب حذف الحساب (Request Account Deletion)

**Endpoint:** `POST /api/v1/auth/request-account-deletion`

**Authentication:** مطلوب (Bearer Token)

**Rate Limiting:** 3 طلبات كل دقيقة

**Headers:**
```
Authorization: Bearer {access_token}
Accept: application/json
Content-Type: application/json
```

**Request Body:**
```json
{}
```
*لا حاجة لإرسال أي بيانات - المستخدم معرف من الـ token*

**Response (200 OK):**
```json
{
    "success": true,
    "message": "تم إرسال رمز حذف الحساب إلى بريدك الإلكتروني.",
    "info": {
        "code_duration": 10,
        "code_expire_at": "2026-01-10 12:30:00"
    }
}
```

**Response Fields:**
- `success`: `boolean` - حالة نجاح العملية
- `message`: `string` - رسالة النجاح
- `info.code_duration`: `integer` - مدة صلاحية الرمز بالدقائق (10 دقائق)
- `info.code_expire_at`: `datetime` - تاريخ ووقت انتهاء صلاحية الرمز

**How it works:**
1. النظام يتحقق من أن المستخدم مسجل الدخول
2. يتم إنشاء رمز عشوائي مكون من 5 أرقام (00000-99999)
3. يتم حفظ الرمز في قاعدة البيانات مع تاريخ انتهاء الصلاحية (10 دقائق من الآن)
4. يتم إرسال الرمز إلى بريد المستخدم الإلكتروني
5. يتم إرجاع رسالة نجاح مع معلومات مدة صلاحية الرمز

**Email Content:**
سيتلقى المستخدم بريداً إلكترونياً بالشكل التالي:
```
مرحباً [اسم المستخدم]، رمز حذف حسابك هو [12345]. صالح لمدة 10 دقيقة.
```

**Errors:**
- `401 Unauthorized`: المستخدم غير مسجل الدخول
- `429 Too Many Requests`: تجاوز عدد الطلبات المسموح بها

---

### 2. تأكيد حذف الحساب (Confirm Account Deletion)

**Endpoint:** `POST /api/v1/auth/confirm-account-deletion`

**Authentication:** مطلوب (Bearer Token)

**Rate Limiting:** 5 طلبات كل دقيقة

**Headers:**
```
Authorization: Bearer {access_token}
Accept: application/json
Content-Type: application/json
```

**Request Body:**
```json
{
    "code": "12345"
}
```

**Request Fields:**
- `code`: `string` (required, digits:5) - رمز حذف الحساب المستلم في البريد الإلكتروني

**Response (200 OK):**
```json
{
    "success": true,
    "message": "تم حذف حسابك بنجاح."
}
```

**How it works:**
1. النظام يتحقق من أن المستخدم مسجل الدخول
2. يتم التحقق من صحة الرمز المدخل مع الرمز المحفوظ في قاعدة البيانات
3. يتم التحقق من أن الرمز لم ينتهِ صلاحيته
4. إذا كان الرمز صحيحاً، يتم تنفيذ عملية الحذف الشاملة:
   - حذف جميع أدوار المستخدم (user roles)
   - حذف جميع الـ tokens (جلسات تسجيل الدخول)
   - حذف جميع الدورات المسجلة (user courses)
   - حذف جميع تقدم الدروس (lesson progress)
   - حذف جميع تقدم المستويات (level progress)
   - حذف جميع محاولات الدروس والإجابات (lesson attempts & answers)
   - حذف جميع محاولات السيناريوهات والإجابات (scenario attempts & answers)
   - حذف جميع الاشتراكات (subscriptions)
   - حذف جميع الإشعارات (notifications)
   - حذف جميع الشهادات (certificates)
   - حذف جميع سجلات النشاط (activity logs)
   - حذف جميع المقالات التي كتبها المستخدم (articles)
   - أخيراً، حذف الحساب نفسه (soft delete)
5. يتم إرجاع رسالة نجاح

**Errors:**
- `401 Unauthorized`: المستخدم غير مسجل الدخول أو الرمز غير صحيح/منتهي الصلاحية
- `422 Validation Error`: رمز غير صحيح (يجب أن يكون 5 أرقام)
- `429 Too Many Requests`: تجاوز عدد الطلبات المسموح بها

**Error Response:**
```json
{
    "success": false,
    "message": "رمز حذف الحساب غير صحيح أو منتهي الصلاحية.",
    "key": "auth.account_deletion_code_invalid_or_expired"
}
```

---

## سير العمل الكامل (Complete Workflow)

### سيناريو استخدام ناجح:

1. **المستخدم يفتح صفحة إعدادات الحساب**
2. **المستخدم يضغط على "حذف الحساب"**
3. **التطبيق يرسل طلب إلى:** `POST /api/v1/auth/request-account-deletion`
   - المستخدم مسجل الدخول (Bearer token في الـ header)
   - لا حاجة لإرسال body
4. **الخادم يرد بـ:**
   - رسالة نجاح
   - مدة صلاحية الرمز (10 دقائق)
5. **المستخدم يتحقق من بريده الإلكتروني**
   - يجد رسالة تحتوي على رمز مكون من 5 أرقام
6. **التطبيق يعرض نافذة إدخال الرمز**
7. **المستخدم يدخل الرمز**
8. **التطبيق يرسل طلب إلى:** `POST /api/v1/auth/confirm-account-deletion`
   - Body: `{ "code": "12345" }`
   - Bearer token في الـ header
9. **الخادم يتحقق من الرمز:**
   - إذا كان صحيحاً: يتم حذف جميع البيانات وإرجاع رسالة نجاح
   - إذا كان غير صحيح: يتم إرجاع خطأ
10. **التطبيق يعرض رسالة نجاح/خطأ**
11. **إذا نجح الحذف:**
    - يتم تسجيل خروج المستخدم تلقائياً
    - يتم توجيه المستخدم إلى الصفحة الرئيسية

---

## أمثلة كود (Code Examples)

### Flutter/Dart Example:

```dart
// Request Account Deletion
Future<void> requestAccountDeletion() async {
  try {
    final response = await http.post(
      Uri.parse('https://api.diplomasi.com/api/v1/auth/request-account-deletion'),
      headers: {
        'Authorization': 'Bearer $accessToken',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({}),
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      final codeExpireAt = DateTime.parse(data['info']['code_expire_at']);
      print('Code sent! Expires at: $codeExpireAt');
    }
  } catch (e) {
    print('Error: $e');
  }
}

// Confirm Account Deletion
Future<void> confirmAccountDeletion(String code) async {
  try {
    final response = await http.post(
      Uri.parse('https://api.diplomasi.com/api/v1/auth/confirm-account-deletion'),
      headers: {
        'Authorization': 'Bearer $accessToken',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'code': code,
      }),
    );

    if (response.statusCode == 200) {
      // Account deleted successfully
      // Logout user and redirect to home
      await logout();
      Navigator.pushNamedAndRemoveUntil(context, '/home', (route) => false);
    } else {
      final error = jsonDecode(response.body);
      print('Error: ${error['message']}');
    }
  } catch (e) {
    print('Error: $e');
  }
}
```

### React Native Example:

```javascript
// Request Account Deletion
const requestAccountDeletion = async () => {
  try {
    const response = await fetch('https://api.diplomasi.com/api/v1/auth/request-account-deletion', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${accessToken}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({}),
    });

    const data = await response.json();
    if (response.ok) {
      const codeExpireAt = new Date(data.info.code_expire_at);
      Alert.alert('Success', `Code sent! Expires at: ${codeExpireAt}`);
    } else {
      Alert.alert('Error', data.message);
    }
  } catch (error) {
    Alert.alert('Error', error.message);
  }
};

// Confirm Account Deletion
const confirmAccountDeletion = async (code) => {
  try {
    const response = await fetch('https://api.diplomasi.com/api/v1/auth/confirm-account-deletion', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${accessToken}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        code: code,
      }),
    });

    const data = await response.json();
    if (response.ok) {
      // Account deleted successfully
      // Logout and redirect
      await logout();
      navigation.reset({
        index: 0,
        routes: [{ name: 'Home' }],
      });
    } else {
      Alert.alert('Error', data.message);
    }
  } catch (error) {
    Alert.alert('Error', error.message);
  }
};
```

---

## ملاحظات مهمة (Important Notes)

### أمان (Security):
1. **Rate Limiting**: يتم تطبيق rate limiting على كلا الـ endpoints لمنع سوء الاستخدام
2. **Authentication Required**: كلا الـ endpoints يتطلبان authentication
3. **Code Expiration**: الرمز صالح لمدة 10 دقائق فقط
4. **One-time Use**: بعد استخدام الرمز بنجاح، لا يمكن استخدامه مرة أخرى

### البيانات المحذوفة (Deleted Data):
عند تأكيد حذف الحساب، يتم حذف **جميع** البيانات المرتبطة بالمستخدم:

- ✅ جميع الأدوار (Roles)
- ✅ جميع الـ Tokens (جلسات تسجيل الدخول)
- ✅ جميع الدورات المسجلة (Courses)
- ✅ جميع تقدم الدروس (Lesson Progress)
- ✅ جميع تقدم المستويات (Level Progress)
- ✅ جميع محاولات الدروس والإجابات (Lesson Attempts & Answers)
- ✅ جميع محاولات السيناريوهات والإجابات (Scenario Attempts & Answers)
- ✅ جميع الاشتراكات (Subscriptions)
- ✅ جميع الإشعارات (Notifications)
- ✅ جميع الشهادات (Certificates)
- ✅ جميع سجلات النشاط (Activity Logs)
- ✅ جميع المقالات التي كتبها المستخدم (Articles)
- ✅ الحساب نفسه (User Account - Soft Delete)

**ملاحظة:** الحذف يتم باستخدام Soft Delete، مما يعني أن البيانات تبقى في قاعدة البيانات ولكن مع علامة `deleted_at`، ولا يمكن الوصول إليها من خلال التطبيق.

### البريد الإلكتروني (Email):
- يجب تكوين إعدادات البريد الإلكتروني في ملف `.env`:
  ```
  MAIL_MAILER=smtp
  MAIL_HOST=smtp.gmail.com
  MAIL_PORT=587
  MAIL_USERNAME=your-email@gmail.com
  MAIL_PASSWORD=your-app-password
  MAIL_ENCRYPTION=tls
  MAIL_FROM_ADDRESS=noreply@diplomasi.com
  MAIL_FROM_NAME="DiplomaSi"
  ```

### معالجة الأخطاء (Error Handling):
- يجب على التطبيق التعامل مع جميع حالات الخطأ الممكنة
- في حالة انتهاء صلاحية الرمز، يجب السماح للمستخدم بطلب رمز جديد
- في حالة إدخال رمز خاطئ 3 مرات، يمكن تطبيق قيود إضافية (اختياري)

---

## اختبار الـ API (Testing)

### باستخدام cURL:

**1. Request Account Deletion:**
```bash
curl -X POST https://api.diplomasi.com/api/v1/auth/request-account-deletion \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{}'
```

**2. Confirm Account Deletion:**
```bash
curl -X POST https://api.diplomasi.com/api/v1/auth/confirm-account-deletion \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"code": "12345"}'
```

### باستخدام Postman:

1. أنشئ طلب جديد من نوع POST
2. أدخل الـ URL: `https://api.diplomasi.com/api/v1/auth/request-account-deletion`
3. في تبويب Headers:
   - `Authorization: Bearer YOUR_ACCESS_TOKEN`
   - `Accept: application/json`
   - `Content-Type: application/json`
4. في تبويب Body، اختر raw و JSON، ثم `{}`
5. أرسل الطلب

---

## الأسئلة الشائعة (FAQ)

**س: ماذا يحدث إذا انتهت صلاحية الرمز؟**
ج: يمكن للمستخدم طلب رمز جديد باستخدام نفس الـ endpoint الأول.

**س: هل يمكن استخدام الرمز أكثر من مرة؟**
ج: لا، الرمز يُستخدم مرة واحدة فقط. بعد استخدامه بنجاح، لا يمكن استخدامه مرة أخرى.

**س: ما هو الفرق بين Soft Delete و Hard Delete؟**
ج: Soft Delete يعني وضع علامة `deleted_at` على البيانات، بينما Hard Delete يعني حذف البيانات نهائياً من قاعدة البيانات. النظام الحالي يستخدم Soft Delete.

**س: هل يمكن استعادة الحساب المحذوف؟**
ج: من الناحية التقنية، نعم (لأنه Soft Delete)، ولكن لا يوجد endpoint لذلك حالياً. يجب التواصل مع الإدارة.

**س: ماذا لو فشل إرسال البريد الإلكتروني؟**
ج: النظام يحاول إرسال البريد، وإذا فشل، يتم تسجيل الخطأ في السجلات ولكن الطلب يعتبر ناجحاً. يجب على المستخدم التحقق من بريده الإلكتروني.

---

## الدعم (Support)

للمزيد من المساعدة أو الإبلاغ عن مشاكل، يرجى التواصل مع فريق التطوير.

---

**آخر تحديث:** 2026-01-10
