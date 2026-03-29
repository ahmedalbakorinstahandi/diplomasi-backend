# الاشتراكات وواجهات الـ API (بما فيها iOS / Apple IAP)

هذا المستند موجه لمطور iOS (أو Flutter/iOS) لربط التطبيق مع الخادم في كل ما يخص الاشتراكات والدفع عبر Apple In-App Purchase.

---

## 1. نظرة عامة

- **Android والويب:** الدفع عبر **Moyasar** (بطاقات، وسائل دفع محفوظة). مسارات الشراء والإلغاء والاستئناف وإعادة المحاولة كلها تعمل عبر الخادم.
- **iOS:** الدفع عبر **Apple In-App Purchase** فقط. التطبيق يشتري من StoreKit ثم يرسل الإيصال للخادم للتحقق وربط الاشتراك. إدارة الاشتراك (إلغاء/استئناف) تتم من **حساب Apple** وليس من التطبيق؛ التطبيق يعرض فقط رابط "إدارة الاشتراك" يفتح إعدادات Apple.

---

## 2. Base URL والترميز

- **Base URL للـ API:** `https://backend.diplomasi.app/api/v1`
- جميع المسارات التالية مسبوقة بهذا الـ prefix ما لم يُذكر خلاف ذلك.
- **الترميز:** JSON. إرسال الهيدر: `Accept: application/json`.
- **المصادقة:** المسارات التي تتطلب تسجيل دخول تستخدم **Bearer Token** (Laravel Sanctum):  
  `Authorization: Bearer <token>`

---

## 3. المسارات العامة (بدون مصادقة)

### 3.1 الحصول على قائمة الخطط

| Method | URL | مصادقة |
|--------|-----|--------|
| GET | `/user/plans` | لا |

**Query (مهم لـ iOS):**

- `platform=ios` — عند الطلب من تطبيق iOS لاستلام السعر المناسب للـ iOS وحقل `ios_product_id`.

**مثال طلب من iOS:**

```
GET /user/plans?platform=ios
```

**استجابة نموذجية (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "الخطة الشهرية",
      "price": "29.99",
      "interval": "monthly",
      "interval_label": "شهري",
      "description": "...",
      "caption": null,
      "is_featured": false,
      "icon_url": "https://...",
      "features": ["..."],
      "created_at": "...",
      "updated_at": "...",
      "ios_product_id": "com.diplomasi.plan_monthly"
    }
  ],
  "meta": { ... }
}
```

- عند `platform=ios`: الحقل `price` يعكس سعر iOS إن وُجد (`ios_price`)، ويُضاف `ios_product_id` إن وُجد. هذا المعرّف يُستخدم في StoreKit لشراء المنتج.

### 3.2 عرض خطة واحدة

| Method | URL | مصادقة |
|--------|-----|--------|
| GET | `/user/plans/{id}` | لا |

يمكن تمرير `platform=ios` في الـ query للحصول على نفس شكل الاستجابة أعلاه (مع `ios_product_id` و سعر iOS إن وُجد).

---

## 4. المسارات المحمية (تحتاج مصادقة)

يُفترض أن كل الطلبات التالية ترسل هيدر:  
`Authorization: Bearer <access_token>`

---

### 4.1 الاشتراك الحالي

| Method | URL | الوصف |
|--------|-----|--------|
| GET | `/user/billing/subscription` | جلب الاشتراك النشط (أو الحالي ضمن فترة السماح) للمستخدم. |

**استجابة (200) — يوجد اشتراك:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 10,
    "plan_id": 1,
    "start_date": "2025-01-01T00:00:00.000000Z",
    "end_date": "2025-02-01T00:00:00.000000Z",
    "status": "active",
    "price": "29.99",
    "currency": "SAR",
    "auto_renew": true,
    "cancel_at_period_end": false,
    "canceled_at": null,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

**استجابة (200) — لا يوجد اشتراك:**

```json
{
  "success": true,
  "data": null
}
```

- على iOS: بعد إتمام الشراء عبر StoreKit والتحقق من الإيصال عبر الخادم، استدعاء هذا المسار يعيد الاشتراك الجديد.

---

### 4.2 تحقق شراء Apple (iOS فقط)

| Method | URL | الوصف |
|--------|-----|--------|
| POST | `/user/ios/purchase/verify` | إرسال إيصال Apple بعد الشراء أو الاستعادة؛ الخادم يتحقق من الإيصال مع Apple ثم ينشئ/يحدّث الاشتراك والفواتير. |

**Body (JSON):**

| الحقل | النوع | مطلوب | الوصف |
|-------|--------|--------|--------|
| `plan_id` | integer | نعم | معرّف الخطة في النظام (يجب أن يطابق الخطة التي تم شراؤها). |
| `product_id` | string | نعم | معرّف المنتج في App Store (مطابق لـ `ios_product_id` في الخطة). |
| `transaction_id` | string | نعم | معرّف المعاملة من Apple (Transaction ID). |
| `receipt` | string | نعم | الإيصال من StoreKit (يُفضّل `serverVerificationData` كما هو دون تعديل). |

**مثال:**

```json
{
  "plan_id": 1,
  "product_id": "com.diplomasi.plan_monthly",
  "transaction_id": "1000000123456789",
  "receipt": "<base64 أو نص serverVerificationData من StoreKit>"
}
```

**استجابة ناجحة (200):**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 10,
    "plan_id": 1,
    "start_date": "...",
    "end_date": "...",
    "status": "active",
    "price": "29.99",
    "currency": "SAR",
    "auto_renew": true,
    "cancel_at_period_end": false,
    "canceled_at": null,
    "created_at": "...",
    "updated_at": "...",
    "plan": { ... }
  }
}
```

**أخطاء محتملة:**

| HTTP | مفتاح الخطأ (إن وُجد) | المعنى |
|------|------------------------|--------|
| 422 | `billing.ios.plan_product_mismatch` | الـ `product_id` لا يطابق الخطة المحددة في النظام. |
| 422 | `billing.ios.verify_failed` | فشل التحقق من الإيصال مع Apple. |
| 422 | `billing.ios.activate_failed` | فشل إنشاء/تحديث الاشتراك بعد التحقق. |

---

### 4.3 مسارات الاشتراك الخاصة بـ Android/ويب (لا تُستخدم على iOS)

| Method | URL | الوصف |
|--------|-----|--------|
| POST | `/user/billing/subscription/purchase` | شراء بخطة باستخدام وسيلة دفع محفوظة (Moyasar). |
| POST | `/user/billing/subscription/purchase-with-payment` | شراء بخطة مع ربط وسيلة دفع جديدة (Moyasar). |
| POST | `/user/billing/subscription/cancel` | إيقاف التجديد التلقائي عند نهاية الفترة. |
| POST | `/user/billing/subscription/resume` | استئناف التجديد التلقائي. |
| POST | `/user/billing/subscription/retry-payment` | إعادة محاولة دفع فاشل. |

- على **iOS** لا يُفترض استدعاء هذه المسارات لإدارة الاشتراك؛ إدارة الاشتراك تتم من إعدادات Apple.

---

### 4.4 مسارات الفواتير والدفع (مشتركة)

| Method | URL | الوصف |
|--------|-----|--------|
| GET | `/user/billing/invoices` | قائمة فواتير المستخدم. |
| GET | `/user/billing/invoices/{id}` | تفاصيل فاتورة. |
| GET | `/user/billing/invoices/{id}/download` | تحميل PDF الفاتورة. |
| GET | `/user/billing/payments` | قائمة معاملات الدفع. |

- اشتراكات Apple تُسجّل كمعاملات دفع (`provider: apple`) وتُصدر لها فواتير؛ يمكن للمستخدم على iOS عرضها من خلال هذه المسارات.

---

### 4.5 وسائل الدفع (Moyasar — لا تُستخدم على iOS)

| Method | URL |
|--------|-----|
| GET | `/user/billing/payment-methods` |
| POST | `/user/billing/payment-methods` |
| POST | `/user/billing/payment-methods/{id}/set-default` |
| DELETE | `/user/billing/payment-methods/{id}` |

- على تطبيق iOS لا يُعرض للمستخدم إدارة وسائل الدفع؛ الدفع يتم عبر Apple فقط.

---

### 4.6 التحقق من دفع Moyasar (بعد شراء من الويب/أندرويد)

| Method | URL | الوصف |
|--------|-----|--------|
| POST | `/user/billing/payments/verify` | التحقق من معاملة Moyasar بعد الدفع (يُستخدم مع `merchant_reference_id`). |

- لا يُستخدم لشراء iOS؛ شراء iOS يُتحقق منه عبر `/user/ios/purchase/verify`.

---

## 5. Webhook إشعارات Apple (لا يحتاج مصادقة مستخدم)

يُستدعى من **Apple** وليس من التطبيق.

| Method | URL | الوصف |
|--------|-----|--------|
| POST | `/ios/notifications` | استقبال App Store Server Notifications V2. |

- **Base الكامل:** `https://backend.diplomasi.app/api/v1/ios/notifications`
- **Body:** جسم الطلب يحتوي على مفتاح `signedPayload` (JWS موقع من Apple). الخادم يتحقق من التوقيع ثم يحدّث حالة الاشتراك (تجديد، إلغاء، انتهاء، إلخ).
- لا يُرسل التطبيق أي شيء إلى هذا المسار؛ يُستخدم فقط في إعدادات App Store Connect كـ Production / Sandbox Server URL.

---

## 6. تدفق الشراء على iOS (من طرف التطبيق)

1. **جلب الخطط:**  
   `GET /user/plans?platform=ios`  
   — التأكد من وجود `ios_product_id` و `price` (سعر iOS) لكل خطة معروضة للشراء.

2. **الشراء من StoreKit:**  
   التطبيق يستخدم `ios_product_id` للخطة المختارة ويطلب المنتج من StoreKit (شراء أو استعادة).

3. **بعد نجاح الشراء/الاستعادة من StoreKit:**  
   التطبيق يجمع:
   - `product_id` (من StoreKit)
   - `transaction_id` (معرّف المعاملة من Apple)
   - `receipt` (يفضّل `serverVerificationData` كما هو)

4. **التحقق من الخادم:**  
   `POST /user/ios/purchase/verify`  
   مع: `plan_id`, `product_id`, `transaction_id`, `receipt`.

5. **عرض الاشتراك الحالي:**  
   بعد نجاح التحقق، استدعاء `GET /user/billing/subscription` يعيد الاشتراك الجديد (أو المحدّث).

---

## 7. ربط الخطط بمنتجات Apple

- كل خطة يُراد بيعها على iOS يجب أن يكون لها في **قاعدة البيانات** (أو لوحة التحكم):
  - **ios_product_id:** معرّف المنتج في App Store Connect (مطابق حرفاً بحرف).
  - **ios_price** (واختياري **ios_currency**): السعر والعملة المعروضان للمستخدم على iOS.

- المنتجات نفسها تُنشأ في **App Store Connect** (In-App Purchases → Auto-Renewable Subscription). معرّف المنتج هناك يجب أن يطابق `ios_product_id` في النظام.

- إن لم تُمرَّر `platform=ios` عند طلب الخطط، أو إن الخطة لا تحتوي على `ios_product_id`، فلن يتم إرجاع معرّف المنتج ولن يعمل الشراء من المتجر بشكل صحيح.

---

## 8. رموز أخطاء الخادم الشائعة (الاشتراكات و Apple)

| المفتاح | المعنى المقترح للمستخدم |
|---------|---------------------------|
| `billing.ios.plan_product_mismatch` | الخطة لا تطابق منتج Apple المحدد. |
| `billing.ios.verify_failed` | فشل التحقق من الإيصال مع Apple. |
| `billing.ios.activate_failed` | فشل تفعيل الاشتراك. |
| `billing.purchase.active_subscription_exists` | يوجد اشتراك نشط يمنع شراء خطة أخرى (عبر Moyasar). |

---

## 9. ملخص للمطور iOS

- استخدم **فقط** المسارات التالية للاشتراكات على iOS:
  - `GET /user/plans?platform=ios` — لعرض الخطط والسعر و `ios_product_id`.
  - `GET /user/billing/subscription` — لمعرفة الاشتراك الحالي.
  - `POST /user/ios/purchase/verify` — بعد كل شراء أو استعادة ناجحة من StoreKit.
- لا تستخدم على iOS: مسارات وسائل الدفع، ولا مسارات الإلغاء/الاستئناف/إعادة المحاولة للاشتراك؛ بدلاً من ذلك اعرض رابط إدارة الاشتراك في إعدادات Apple (مثل: `https://apps.apple.com/account/subscriptions`).
- الإيصال المُرسَل في `verify` يجب أن يكون كما يوفره StoreKit (يفضّل `serverVerificationData`) دون تعديل.

---

*آخر تحديث للمستند وفق بنية المسارات والتحقق الحالية في الخادم.*
