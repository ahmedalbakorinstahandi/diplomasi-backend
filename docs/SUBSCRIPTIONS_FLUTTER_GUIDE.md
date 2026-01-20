# دليل تكامل نظام الاشتراكات مع Flutter

## نظرة عامة

هذا الدليل يشرح بالتفصيل كيفية تكامل تطبيق Flutter مع نظام الاشتراكات في Diplomasi Backend. النظام يستخدم Stripe Payment Intent مع Ephemeral Key لعرض البطاقات المحفوظة للمستخدم.

## المتطلبات الأساسية

### 1. تثبيت Stripe SDK في Flutter

```yaml
# pubspec.yaml
dependencies:
  flutter_stripe: ^10.0.0
  http: ^1.1.0
```

### 2. إعداد Stripe

في Flutter، قم بإعداد Stripe Publishable Key (ستحصل عليه من Backend):

```dart
import 'package:flutter_stripe/flutter_stripe.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // إعداد Stripe Publishable Key
  Stripe.publishableKey = "pk_test_..."; // من Backend
  Stripe.merchantIdentifier = "merchant.com.diplomasi";
  
  runApp(MyApp());
}
```

## البنية العامة

### تدفق العمل الكامل

```
1. المستخدم يختار خطة
   ↓
2. Flutter يطلب إعداد الدفع من Backend
   ↓
3. Backend ينشئ Payment Intent و Ephemeral Key
   ↓
4. Backend يرجع client_secret, customer_id, ephemeral_key
   ↓
5. Flutter يعرض Stripe Payment Sheet (مع البطاقات المحفوظة)
   ↓
6. المستخدم يختار/يدخل بطاقة ويدفع
   ↓
7. Stripe يؤكد الدفع
   ↓
8. Flutter يرسل payment_intent_id للـ Backend
   ↓
9. Backend ينشئ Subscription
   ↓
10. Backend يرجع Subscription details
```

## API Endpoints

### Base URL
```
https://your-api-domain.com/api/v1/user
```

### Authentication
جميع الـ endpoints تتطلب Bearer Token في Header:
```
Authorization: Bearer {token}
Accept-Language: ar
Content-Type: application/json
```

## 1. عرض الخطط المتاحة

### Endpoint
```
GET /api/v1/user/plans
```

### cURL Example
```bash
curl -X GET "https://your-api-domain.com/api/v1/user/plans" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept-Language: ar"
```

### Response
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Basic Plan",
      "price": 9.99,
      "interval": "monthly",
      "description": "Basic features",
      "features": ["Feature 1", "Feature 2"],
      "stripe_plan_id": "plan_basic_monthly"
    }
  ]
}
```

## 2. إعداد الدفع (Prepare Payment)

### Endpoint
```
POST /api/v1/user/subscriptions/prepare-payment
```

### Request Body
```json
{
  "plan_id": 1
}
```

### cURL Example
```bash
curl -X POST "https://your-api-domain.com/api/v1/user/subscriptions/prepare-payment" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept-Language: ar" \
  -H "Content-Type: application/json" \
  -d '{
    "plan_id": 1
  }'
```

### Response
```json
{
  "success": true,
  "data": {
    "payment": {
      "client_secret": "pi_1234567890_secret_...",
      "customer_id": "cus_1234567890",
      "ephemeral_key": "ek_test_1234567890",
      "amount": 9.99,
      "currency": "USD"
    },
    "plan": {
      "id": 1,
      "name": "Basic Plan",
      "price": 9.99
    }
  }
}
```

### شرح البيانات المرجعة

- **client_secret**: مطلوب لعرض Stripe Payment Sheet
- **customer_id**: معرف العميل في Stripe (يربط البطاقات المحفوظة)
- **ephemeral_key**: مطلوب لعرض البطاقات المحفوظة للمستخدم
- **amount**: المبلغ المطلوب
- **currency**: العملة

### استخدام في Flutter

```dart
// 1. استدعي prepare-payment endpoint
final response = await http.post(
  Uri.parse('$baseUrl/subscriptions/prepare-payment'),
  headers: {
    'Authorization': 'Bearer $token',
    'Accept-Language': 'ar',
    'Content-Type': 'application/json',
  },
  body: json.encode({
    'plan_id': selectedPlanId,
  }),
);

final data = json.decode(response.body);
final paymentData = data['data']['payment'];

// 2. إعداد Stripe مع Ephemeral Key
await Stripe.instance.initPaymentSheet(
  paymentSheetParameters: SetupPaymentSheetParameters(
    paymentIntentClientSecret: paymentData['client_secret'],
    customerId: paymentData['customer_id'],
    customerEphemeralKeySecret: paymentData['ephemeral_key'],
    merchantDisplayName: 'Diplomasi',
  ),
);

// 3. عرض Payment Sheet
await Stripe.instance.presentPaymentSheet();

// 4. بعد نجاح الدفع، احصل على payment_intent_id
// (ستحصل عليه من Stripe callback)
```

## 3. إنشاء الاشتراك (بعد تأكيد الدفع)

### Endpoint
```
POST /api/v1/user/subscriptions
```

### Request Body
```json
{
  "plan_id": 1,
  "payment_intent_id": "pi_1234567890",
  "auto_renew": true
}
```

### cURL Example
```bash
curl -X POST "https://your-api-domain.com/api/v1/user/subscriptions" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept-Language: ar" \
  -H "Content-Type: application/json" \
  -d '{
    "plan_id": 1,
    "payment_intent_id": "pi_1234567890",
    "auto_renew": true
  }'
```

### Response
```json
{
  "success": true,
  "message": "Subscription created successfully",
  "data": {
    "id": 1,
    "user_id": 123,
    "plan_id": 1,
    "plan": {
      "id": 1,
      "name": "Basic Plan",
      "price": 9.99
    },
    "start_date": "2026-01-20",
    "end_date": "2026-02-20",
    "status": "active",
    "price": 9.99,
    "currency": "USD",
    "auto_renew": true,
    "cancel_at_period_end": false
  }
}
```

### ملاحظات مهمة

- يجب أن يكون `payment_intent_id` من Payment Intent الذي تم تأكيده بنجاح
- `auto_renew` اختياري (افتراضي: true)
- البطاقة المستخدمة في الدفع سيتم حفظها تلقائياً في Stripe

## 4. الحصول على الاشتراك الحالي

### Endpoint
```
GET /api/v1/user/subscriptions/current
```

### cURL Example
```bash
curl -X GET "https://your-api-domain.com/api/v1/user/subscriptions/current" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept-Language: ar"
```

### Response
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 123,
    "plan_id": 1,
    "plan": {
      "id": 1,
      "name": "Basic Plan",
      "price": 9.99
    },
    "start_date": "2026-01-20",
    "end_date": "2026-02-20",
    "status": "active",
    "price": 9.99,
    "currency": "USD",
    "auto_renew": true,
    "cancel_at_period_end": false
  }
}
```

### ملاحظة
إذا لم يكن هناك اشتراك نشط، `data` سيكون `null`.

## 5. إلغاء التجديد التلقائي

### Endpoint
```
POST /api/v1/user/subscriptions/{id}/cancel-auto-renew
```

### cURL Example
```bash
curl -X POST "https://your-api-domain.com/api/v1/user/subscriptions/1/cancel-auto-renew" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept-Language: ar" \
  -H "Content-Type: application/json"
```

### Response
```json
{
  "success": true,
  "message": "Auto-renewal cancelled",
  "data": {
    "id": 1,
    "auto_renew": false,
    "cancel_at_period_end": true,
    ...
  }
}
```

### ملاحظات
- الاشتراك سيستمر حتى `end_date`
- بعد `end_date` لن يتم التجديد تلقائياً
- يمكن استئناف التجديد التلقائي لاحقاً

## 6. استئناف التجديد التلقائي

### Endpoint
```
POST /api/v1/user/subscriptions/{id}/resume-auto-renew
```

### cURL Example
```bash
curl -X POST "https://your-api-domain.com/api/v1/user/subscriptions/1/resume-auto-renew" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept-Language: ar" \
  -H "Content-Type: application/json"
```

### Response
```json
{
  "success": true,
  "message": "Auto-renewal resumed",
  "data": {
    "id": 1,
    "auto_renew": true,
    "cancel_at_period_end": false,
    ...
  }
}
```

## 7. ترقية الاشتراك

### Endpoint
```
POST /api/v1/user/subscriptions/{id}/upgrade
```

### Request Body
```json
{
  "plan_id": 2
}
```

### cURL Example
```bash
curl -X POST "https://your-api-domain.com/api/v1/user/subscriptions/1/upgrade" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept-Language: ar" \
  -H "Content-Type: application/json" \
  -d '{
    "plan_id": 2
  }'
```

### Response
```json
{
  "success": true,
  "message": "Subscription upgraded successfully",
  "data": {
    "id": 1,
    "plan_id": 2,
    "plan": {
      "id": 2,
      "name": "Premium Plan",
      "price": 19.99
    },
    ...
  }
}
```

### كيف تعمل الترقية

1. **حساب القيمة المتبقية**: النظام يحسب القيمة المتبقية من الاشتراك الحالي
2. **حساب المبلغ المطلوب**: المبلغ الجديد - القيمة المتبقية
3. **الدفع**: يتم خصم المبلغ المتبقي من السعر الجديد
4. **التحديث**: يتم تحديث الاشتراك للخطة الجديدة

### مثال
- اشتراك شهري: $10/شهر
- تم الدفع قبل 15 يوم
- القيمة المتبقية: $5
- خطة جديدة: $20/شهر
- المبلغ المطلوب: $20 - $5 = $15

## 8. البطاقات المحفوظة (Payment Methods)

### كيف تعمل البطاقات المحفوظة

عند استخدام `prepare-payment` endpoint:
1. Backend ينشئ/يستخدم Stripe Customer للمستخدم
2. Backend ينشئ Ephemeral Key
3. عند عرض Stripe Payment Sheet في Flutter مع Ephemeral Key، ستظهر البطاقات المحفوظة تلقائياً
4. المستخدم يمكنه اختيار بطاقة محفوظة أو إضافة بطاقة جديدة
5. البطاقة الجديدة سيتم حفظها تلقائياً في Stripe

### ملاحظات مهمة

- **لا حاجة لـ endpoints منفصلة**: البطاقات تُدار تلقائياً من خلال Stripe Payment Sheet
- **Ephemeral Key**: يسمح للـ Flutter بالوصول إلى البطاقات المحفوظة للمستخدم
- **Customer ID**: يربط جميع البطاقات بنفس المستخدم

## 9. مثال كامل: تدفق إنشاء اشتراك

### الخطوة 1: عرض الخطط
```bash
curl -X GET "https://your-api-domain.com/api/v1/user/plans" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept-Language: ar"
```

### الخطوة 2: إعداد الدفع
```bash
curl -X POST "https://your-api-domain.com/api/v1/user/subscriptions/prepare-payment" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept-Language: ar" \
  -H "Content-Type: application/json" \
  -d '{"plan_id": 1}'
```

**النتيجة:**
```json
{
  "success": true,
  "data": {
    "payment": {
      "client_secret": "pi_...",
      "customer_id": "cus_...",
      "ephemeral_key": "ek_...",
      "amount": 9.99,
      "currency": "USD"
    }
  }
}
```

### الخطوة 3: في Flutter - عرض Payment Sheet

```dart
// إعداد Payment Sheet
await Stripe.instance.initPaymentSheet(
  paymentSheetParameters: SetupPaymentSheetParameters(
    paymentIntentClientSecret: paymentData['client_secret'],
    customerId: paymentData['customer_id'],
    customerEphemeralKeySecret: paymentData['ephemeral_key'],
    merchantDisplayName: 'Diplomasi',
  ),
);

// عرض Payment Sheet
try {
  await Stripe.instance.presentPaymentSheet();
  
  // الدفع نجح - احصل على payment_intent_id
  // (يمكنك الحصول عليه من Stripe callback أو من Payment Intent)
  
} on StripeException catch (e) {
  // معالجة الأخطاء
  print('Error: ${e.error.message}');
}
```

### الخطوة 4: إنشاء الاشتراك
```bash
curl -X POST "https://your-api-domain.com/api/v1/user/subscriptions" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept-Language: ar" \
  -H "Content-Type: application/json" \
  -d '{
    "plan_id": 1,
    "payment_intent_id": "pi_1234567890",
    "auto_renew": true
  }'
```

## 10. معالجة الأخطاء

### Error Response Format
```json
{
  "success": false,
  "message": "Error message in Arabic"
}
```

### Common Errors

#### 401 Unauthorized
```json
{
  "success": false,
  "message": "Unauthorized"
}
```
**الحل**: تأكد من إرسال Bearer Token صحيح

#### 404 Not Found
```json
{
  "success": false,
  "message": "Plan not found"
}
```
**الحل**: تأكد من `plan_id` صحيح

#### 400 Bad Request
```json
{
  "success": false,
  "message": "Payment intent not succeeded"
}
```
**الحل**: تأكد من أن Payment Intent تم تأكيده بنجاح قبل إنشاء الاشتراك

## 11. التحقق من حالة الاشتراك

### استخدام getCurrent endpoint

```bash
curl -X GET "https://your-api-domain.com/api/v1/user/subscriptions/current" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept-Language: ar"
```

إذا كان `data` هو `null`، يعني المستخدم غير مشترك.

## 12. Stripe Payment Sheet في Flutter

### المتطلبات

1. **Publishable Key**: من Backend (يتم إعداده مرة واحدة)
2. **Client Secret**: من `prepare-payment` endpoint
3. **Customer ID**: من `prepare-payment` endpoint
4. **Ephemeral Key**: من `prepare-payment` endpoint

### الكود الأساسي

```dart
import 'package:flutter_stripe/flutter_stripe.dart';

// 1. إعداد Stripe (مرة واحدة في بداية التطبيق)
Stripe.publishableKey = "pk_test_...";

// 2. إعداد Payment Sheet
await Stripe.instance.initPaymentSheet(
  paymentSheetParameters: SetupPaymentSheetParameters(
    paymentIntentClientSecret: clientSecret,
    customerId: customerId,
    customerEphemeralKeySecret: ephemeralKey,
    merchantDisplayName: 'Diplomasi',
  ),
);

// 3. عرض Payment Sheet
await Stripe.instance.presentPaymentSheet();

// 4. بعد النجاح، استخدم payment_intent_id لإنشاء الاشتراك
```

## 13. الأمان

### Important Notes

1. **لا تخزن بيانات البطاقة**: جميع البيانات تُدار من خلال Stripe
2. **استخدم HTTPS فقط**: تأكد من استخدام HTTPS في جميع الطلبات
3. **احفظ Tokens بشكل آمن**: استخدم secure storage للـ tokens
4. **Ephemeral Key**: له صلاحية محدودة (عادة 24 ساعة)

## 14. Testing

### Test Cards (Stripe Test Mode)

```
Card Number: 4242 4242 4242 4242
Expiry: أي تاريخ في المستقبل
CVC: أي 3 أرقام
ZIP: أي 5 أرقام
```

### Test Scenarios

1. إنشاء اشتراك جديد
2. استخدام بطاقة محفوظة
3. إضافة بطاقة جديدة
4. إلغاء التجديد التلقائي
5. استئناف التجديد التلقائي
6. ترقية الاشتراك

## 15. Troubleshooting

### مشكلة: Payment Sheet لا يظهر
**الحل**: 
- تأكد من إعداد `Stripe.publishableKey` بشكل صحيح
- تأكد من `client_secret` صحيح
- تأكد من `ephemeral_key` صحيح

### مشكلة: البطاقات المحفوظة لا تظهر
**الحل**:
- تأكد من إرسال `customer_id` و `ephemeral_key` بشكل صحيح
- تأكد من أن المستخدم لديه `stripe_customer_id` في Backend

### مشكلة: Payment Intent not succeeded
**الحل**:
- تأكد من أن الدفع تم بنجاح في Stripe Payment Sheet
- انتظر قليلاً قبل إنشاء الاشتراك (لضمان تحديث Stripe)

## 16. Resources

- [Stripe Flutter SDK Documentation](https://stripe.dev/stripe-flutter/)
- [Stripe Payment Sheet Guide](https://stripe.com/docs/payments/accept-a-payment?platform=flutter)
- [Stripe API Reference](https://stripe.com/docs/api)
- [Diplomasi Backend API Documentation](./SUBSCRIPTIONS_DASHBOARD_GUIDE.md)

## 17. Support

للدعم الفني، يرجى التواصل مع فريق التطوير.
