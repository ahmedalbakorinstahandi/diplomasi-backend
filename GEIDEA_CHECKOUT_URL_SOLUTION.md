# حل مشكلة checkout_url في Geidea Integration

## المشكلة

Flutter لا يستطيع فتح صفحة الدفع لأن `checkout_url` غير موجود في `prepare-payment` response.

## الوضع الحالي

- Geidea API يرجع HTTP 204 (No Content) عند إنشاء order
- لا يوجد data في الـ response body
- `checkout_url`, `session_id`, `order_id` كلها `null` في البداية

## الحلول المطبقة

### 1. إضافة `checkout_url` إلى `payment-status` response ✅

**الملف**: `app/Http/Controllers/Billing/SubscriptionController.php`

**التعديل**: في `getPaymentStatus()` method (السطر 527-545)

```php
// Add checkout_url if available (may come from webhook or API verification)
if ($paymentAttempt->checkout_url) {
    $responseData['checkout_url'] = $paymentAttempt->checkout_url;
}
```

**الفائدة**: 
- Flutter يمكنه استخدام `payment-status` endpoint للتحقق من `checkout_url`
- إذا جاء webhook، `checkout_url` سيكون متوفراً

### 2. استخراج `checkout_url` من Geidea API في self-healing ✅

**الملف**: `app/Http/Controllers/Billing/SubscriptionController.php`

**التعديل**: في `getPaymentStatus()` method - self-healing logic (السطر 472-525)

```php
// Extract checkout_url from verified order if available
$checkoutUrl = $verifiedOrder->checkoutUrl ?? $verifiedOrder->checkout_url ?? ...;

// Update checkout_url if available
if ($checkoutUrl && !$paymentAttempt->checkout_url) {
    $updateData['checkout_url'] = $checkoutUrl;
}
```

**الفائدة**:
- إذا كان الـ payment attempt قديم (>60s) ولا يزال pending، يتم التحقق من Geidea API
- إذا كان `checkout_url` متوفراً في الـ response، يتم حفظه

### 3. استخراج `checkout_url` من webhook ✅

**الملف**: `app/Http/Controllers/Billing/GeideaWebhookController.php`

**التعديل**: في `handle()` method و `processPaymentCompleted()`, `processPaymentFailed()`, `processPaymentCanceled()`

```php
// Extract checkout_url from verified order
$checkoutUrl = $verifiedOrder->checkoutUrl ?? $verifiedOrder->checkout_url ?? ...;

// Update checkout_url if available
if ($checkoutUrl && !$paymentAttempt->checkout_url) {
    $updateData['checkout_url'] = $checkoutUrl;
}
```

**الفائدة**:
- عندما يأتي webhook من Geidea، يتم استخراج `checkout_url` من الـ verified order
- يتم حفظه في `PaymentAttempt` للاستخدام لاحقاً

### 4. محاولة سريعة للحصول على order بعد الإنشاء ✅

**الملف**: `app/Services/GeideaService.php`

**التعديل**: في `createPaymentSession()` method (السطر 125-155)

```php
// Try ONE quick attempt to get order by merchantReferenceId (non-blocking)
if (isset($data['merchantReferenceId'])) {
    usleep(500000); // 0.5 seconds
    $order = $this->getOrderByMerchantReference($data['merchantReferenceId']);
    if ($order) {
        return $order; // Contains checkout_url if available
    }
}
```

**الفائدة**:
- محاولة واحدة سريعة (0.5 ثانية) للحصول على order
- إذا نجحت، `checkout_url` سيكون متوفراً مباشرة
- إذا فشلت، webhook سيوفر البيانات لاحقاً

## Flow المحدث

### Scenario 1: checkout_url متوفر مباشرة (محظوظ)
```
1. prepare-payment → Geidea API → HTTP 204
2. Quick fetch attempt → Success → checkout_url متوفر
3. Response → يحتوي checkout_url ✅
4. Flutter → يفتح checkout_url مباشرة
```

### Scenario 2: checkout_url يأتي من webhook (الأكثر احتمالاً)
```
1. prepare-payment → Geidea API → HTTP 204
2. Quick fetch attempt → Failed (Geidea لم يجهز بعد)
3. Response → merchant_reference فقط
4. Flutter → يستخدم merchant_reference مع Geidea SDK
5. User → يكمل الدفع
6. Geidea → يرسل webhook
7. Webhook → يستخرج checkout_url من verified order
8. PaymentAttempt → يتم تحديثه بـ checkout_url
9. Flutter → يستخدم payment-status → يحصل على checkout_url ✅
```

### Scenario 3: checkout_url يأتي من self-healing
```
1. prepare-payment → merchant_reference فقط
2. Flutter → يبدأ polling payment-status
3. بعد 60+ ثانية → self-healing يتحقق من Geidea API
4. Geidea API → يرجع order مع checkout_url
5. PaymentAttempt → يتم تحديثه
6. payment-status response → يحتوي checkout_url ✅
```

## كيفية استخدام Flutter

### Option 1: استخدام merchant_reference مباشرة (الأفضل)
```dart
// بعد prepare-payment
final merchantRef = response.data['merchant_reference'];

// استخدام Geidea Flutter SDK
await GeideaSDK.openPayment(
  merchantReference: merchantRef,
  amount: amount,
  currency: 'SAR',
);
```

### Option 2: Polling payment-status
```dart
// بعد prepare-payment
final merchantRef = response.data['merchant_reference'];

// Poll payment-status endpoint
while (true) {
  final statusResponse = await api.getPaymentStatus(merchantRef);
  
  if (statusResponse.data['checkout_url'] != null) {
    // checkout_url متوفر الآن!
    await launchUrl(statusResponse.data['checkout_url']);
    break;
  }
  
  if (statusResponse.data['status'] == 'completed') {
    // Payment completed
    break;
  }
  
  await Future.delayed(Duration(seconds: 2));
}
```

## الخلاصة

✅ **تم تطبيق جميع الحلول المقترحة**

1. ✅ `checkout_url` في `payment-status` response
2. ✅ استخراج `checkout_url` من Geidea API في self-healing
3. ✅ استخراج `checkout_url` من webhook
4. ✅ محاولة سريعة للحصول على order بعد الإنشاء

**النتيجة**: Flutter الآن لديه 3 طرق للحصول على `checkout_url`:
- مباشرة من `prepare-payment` (إذا نجحت المحاولة السريعة)
- من `payment-status` endpoint (بعد webhook أو self-healing)
- استخدام `merchant_reference` مباشرة مع Geidea SDK (الأفضل)
