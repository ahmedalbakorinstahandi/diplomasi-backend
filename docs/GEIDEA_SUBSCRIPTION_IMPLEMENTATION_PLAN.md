# خطة تنفيذ الاشتراكات والدفع عبر Geidea

## 1. نطاق النظام (بدون ترقية/تخفيض)

| المطلوب | الوصف |
|---------|--------|
| **اشتراك** | المستخدم يختار خطة → يدفع (مرة واحدة أو أول دفعة مع تجديد تلقائي) → ننشئ اشتراكاً محلياً |
| **إلغاء الاشتراك** | المستخدم يلغي → نلغي عند Geidea (إن وُجد) ونحدث الحالة محلياً |
| **التجديد التلقائي** | اختيار: إما **Geidea تخصم تلقائياً** (RecurringPayment) أو **لا تجديد تلقائي** (دفعة واحدة ثم انتهاء بعد المدة) |

لا يوجد: ترقية خطة، تخفيض خطة، تغيير خطة.

---

## 2. ما هو متوفر لدينا وما الناقص

### 2.1 متوفر

| المصدر | المحتوى |
|--------|---------|
| **Create Session (CHECKOUT V2)** | `POST /payment-intent/api/v2/direct/session` — حقول مطلوبة: `amount`, `currency`, `signature`. اختياري: `merchantReferenceId` (UUID), `callbackUrl`, `returnUrl`, **`subscriptionId`** (GUID من Create Subscription)، `cardOnFile`, `timestamp`, `appearance`, `language`, `customer`. الاستجابة: `session.id`, `session.expiryDate`, `session.status`, `session.merchantReferenceId`. |
| **Create Subscription (Subscriptions v2)** | `POST /subscriptions/api/v1/direct/subscription` — مطلوب: `recurringPaymentAmount`, `currency`, `cycleInterval` (day/week/month/year), `cycleFrequency`, `typeOfPayment` (RecurringPayment | RecurringLink), `customerRequest` أو `customerId`, `signature`. اختياري: `description`, `startDate`, `endDate`, `numberOfPayments`, `isFirstPmtPBL` (false عند استخدام Session لجباية أول دفعة). الاستجابة: `subscription.subscriptionId`, `subscription.status` (Created), `subscription.cycleInterval`, `subscription.cycleFrequency`. |
| **Get Subscription** | `GET /subscriptions/api/v1/direct/subscription/{subscriptionid}` + body `signature`. |
| **Cancel Subscription** | `POST /subscriptions/api/v1/direct/subscription/{subscriptionid}/cancel` + body `signature`. |
| **Callback/Webhook** | في الـ requirements: الـ callback يحوي كائن `order` مع `orderId`, `status`, `merchantReferenceId`, `transactions[]` (كل transaction له `type`, `status`, `paymentAttemptId`). التحقق: توقيع من ربط `MerchantPublicKey, OrderAmount, OrderCurrency, OrderId, Status, MerchantReferenceId, timeStamp` ثم HMAC-SHA256 بـ API Password ثم Base64. |
| **Recurring / MIT** | Tokenization: `cardOnFile: true`, `cofAgreement: { id, type: "Unscheduled" }`, `initiatedBy: "Internet"`. توقيع الـ session: `merchant_public_key + session_id + timestamp` ثم HMAC-SHA256 بـ api_password ثم Base64. |
| **Signature (Refund مثال)** | Concatenate (TimeStamp, MerchantPublicKey, RefundAmount, OrderID) → HMAC-SHA256 بـ API Password → Base64. |

### 2.2 ناقص (يُستحسن تأكيده من وثائق Geidea)

| البند | الاستخدام |
|-------|-----------|
| **صيغة الـ signature لـ Create Session** | الوثيقة تشير إلى [creating signature](https://docs.geidea.net/docs/geidea-checkout-v2#signature). نحتاج: ترتيب الحقول (مثلاً: publicKey, amount, currency, merchantReferenceId, timestamp) وطريقة الـ hash. |
| **صيغة الـ signature لـ Create Subscription** | نفس الفكرة: أي حقول تُدخل في الـ concatenation وبأي ترتيب. |
| **صيغة الـ signature لـ Get/Cancel Subscription** | هل يدخل فيها `subscriptionid` فقط أم مع timestamp؟ |
| **Fetch Order by Merchant Reference** | للتحقق من حالة الدفع من الـ backend (لو الـ callback تأخر أو للـ polling). مسار الـ API وطريقة الاستدعاء. |
| **Test Cards (Sandbox)** | أرقام بطاقات وتواريخ انتهاء و CVV للاختبار. |

بمجرد توفير صيغ الـ signature و (إن أمكن) Fetch by Merchant Reference و Test Cards، يمكن تنفيذ الخطة كما هي أدناه.

---

## 3. وضعا التشغيل: مع تجديد تلقائي / بدونه

### 3.1 مع تجديد تلقائي (Geidea تخصم تلقائياً)

1. **إنشاء اشتراك في Geidea**  
   استدعاء Create Subscription مع:
   - `typeOfPayment`: `RecurringPayment`
   - `isFirstPmtPBL`: `false` (أول دفعة عبر HPP وليس عبر رابط بريد)
   - `recurringPaymentAmount`, `currency`, `cycleInterval`, `cycleFrequency` من الخطة المحلية
   - `customerRequest`: اسم، بريد، هاتف (من المستخدم المسجّل)
   - `signature` حسب وثائق Geidea

2. **إنشاء جلسة دفع (أول دفعة)**  
   استدعاء Create Session مع:
   - `amount` = سعر الخطة، `currency`
   - **`subscriptionId`** = القيمة المُرجعة من Create Subscription
   - `merchantReferenceId` = UUID نولّده نحن (ربط مع جدولنا)
   - `callbackUrl`, `returnUrl`, `signature`, `timestamp`

3. **التطبيق (Flutter)**  
   يفتح HPP باستخدام `session.id` (أو رابط الـ checkout من الاستجابة). بعد الدفع، Geidea ترسل النتيجة إلى `callbackUrl` وتُحوّل المستخدم إلى `returnUrl`.

4. **الـ Webhook**  
   عند نجاح الدفع: نحدّث حالة المحاولة، ننشئ (أو نربط) الاشتراك المحلي، نخزّن `geidea_subscription_id` و `geidea_order_id`. التجديدات التالية Geidea تنفذها وتُعلمنا (إن وُجد webhook للتجديد) أو نعتمد حالة الاشتراك من Get Subscription.

5. **الإلغاء**  
   عند طلب المستخدم إلغاء الاشتراك: استدعاء Cancel Subscription عند Geidea ثم تحديث حالة الاشتراك محلياً إلى `cancelled`.

### 3.2 بدون تجديد تلقائي (دفعة واحدة فقط)

1. **لا نستدعي Create Subscription** في Geidea.

2. **إنشاء جلسة دفع فقط**  
   Create Session **بدون** `subscriptionId`:
   - `amount`, `currency`, `merchantReferenceId`, `callbackUrl`, `returnUrl`, `signature`, `timestamp`

3. **بعد نجاح الدفع (من الـ callback)**  
   ننشئ اشتراكاً محلياً لفترة واحدة (من تاريخ اليوم حتى نهاية المدة حسب الخطة: شهر/ستة أشهر/سنة). لا يوجد تجديد تلقائي؛ بعد انتهاء المدة يظهر للمستخدم "انتهى الاشتراك" ويمكنه شراء اشتراك جديد (دفعة جديدة).

4. **الإلغاء**  
   فقط تحديث محلي: إلغاء الاشتراك أو "إلغاء التجديد" غير موجود لأن هناك دفعة واحدة فقط. يمكن اعتبار "إلغاء" = إيقاف الوصول فوراً (تحديث حالة إلى `cancelled`).

---

## 4. إعداد قاعدة البيانات والنماذج

### 4.1 جداول/حقول مقترحة

- **جدول الاشتراكات (الحالي)**  
  - إضافة (إن لم تكونا موجودتين):
    - `geidea_subscription_id` (nullable) — يُملأ فقط في وضع "مع تجديد تلقائي".
    - `geidea_order_id` (nullable) — آخر order مرتبط بالدفعة (أول دفعة أو تجديد إن وُجد).

- **جدول محاولات الدفع (Payment Attempts)** — إعادة استخدام أو إنشاء بسيط:
  - `id`, `user_id`, `plan_id`, `merchant_reference` (UUID), `amount`, `currency`, `status` (initiated | pending | completed | failed | cancelled), `geidea_session_id`, `geidea_order_id`, `subscription_id` (FK nullable، بعد إنشاء الاشتراك), `metadata` (JSON)، `created_at`, `updated_at`.

لا حاجة لحقول ترقية/تخفيض أو تغيير خطة.

### 4.2 النماذج (Models)

- **Subscription**: إضافة الـ fillable والعلاقات لـ `geidea_subscription_id`, `geidea_order_id` إن وُجدتا.
- **PaymentAttempt** (أو الاسم الحالي): ربط مع User, Plan, Subscription؛ استخدام `merchant_reference` لربط الطلبات مع الـ callback.

---

## 5. الـ Backend (Laravel)

### 5.1 الخدمات

- **GeideaService** (أو GeideaCheckoutService):
  - `createSubscription(array $params): object` — استدعاء Create Subscription، إرجاع `subscriptionId` والحقول الضرورية.
  - `createSession(array $params): object` — استدعاء Create Session (مع أو بدون `subscriptionId`)، إرجاع `session.id` وكل ما يلزم لفتح HPP.
  - `cancelSubscription(string $geideaSubscriptionId): void` — استدعاء Cancel Subscription.
  - `getSubscription(string $geideaSubscriptionId): object` — استدعاء Get Subscription (للمزامنة أو التحقق).
  - `generateSignature(string $payload, string $apiPassword): string` — HMAC-SHA256 ثم Base64 (وفق صيغة Geidea لكل استدعاء).
  - (اختياري) `fetchOrderByMerchantReference(string $merchantReference): ?object` — عند توفر الـ API.

يُنصح بوضع قاعدة الـ URL واختيار البيئة (KSA / UAE / Egypt) من الإعدادات أو `.env`.

### 5.2 الكونترولرز والـ Routes

- **Prepare Payment (للمستخدم المصادق)**  
  - مدخل واحد: `plan_id` + اختياري `auto_renew` (boolean).
  - إذا `auto_renew === true`: استدعاء Create Subscription ثم Create Session مع `subscriptionId`.
  - إذا `auto_renew === false`: استدعاء Create Session فقط (بدون `subscriptionId`).
  - إنشاء سجل في جدول محاولات الدفع مع `merchant_reference` (UUID)، وحفظ `geidea_session_id` و `geidea_subscription_id` (إن وُجد).
  - إرجاع للتطبيق: `session_id` (أو ما يلزم لفتح HPP)، `merchant_reference`, `checkout_url` إن وُجدت في الاستجابة.

- **Webhook (Callback) Geidea**  
  - مسار عام (بدون مصادقة): مثلاً `POST /api/v1/webhooks/geidea`.
  - التحقق من الـ signature باستخدام الصيغة الموثقة (OrderAmount, OrderCurrency, OrderId, Status, MerchantReferenceId, timeStamp…).
  - استخراج `orderId`, `merchantReferenceId`, `status`.
  - البحث عن محاولة الدفع بـ `merchant_reference = merchantReferenceId`.
  - عند النجاح: تحديث المحاولة، إنشاء/ربط الاشتراك المحلي، تحديث `geidea_order_id` و `geidea_subscription_id` إن وُجد.
  - عند الفشل: تحديث حالة المحاولة إلى failed.

- **Cancel Subscription (للمستخدم)**  
  - مدخل: معرف الاشتراك المحلي (الذي يخص المستخدم).
  - إذا كان الاشتراك يحوي `geidea_subscription_id`: استدعاء GeideaService.cancelSubscription.
  - تحديث حالة الاشتراك محلياً إلى `cancelled`.

- **Payment Status (اختياري)**  
  - مدخل: `merchant_reference`.  
  - إرجاع حالة المحاولة من الجدول المحلي (وإن أردت التحقق من Geidea عبر Fetch by Merchant Reference عند توفر الـ API).

### 5.3 الإعدادات

- في `config/services.php`: إضافة مفتاح `geidea` مع:
  - `public_key`, `api_password`, `base_url` (حسب الدولة)، واختياري `environment` (sandbox/production).
- قراءة القيم من `.env` وعدم تخزين كلمة الـ API في الكود.

---

## 6. التطبيق (Flutter)

- بعد استدعاء Prepare Payment، الحصول على `session_id` (أو معادل لفتح HPP).
- فتح صفحة الدفع Geidea (WebView أو SDK إن وُجد) باستخدام هذا المُعرف.
- عند العودة (returnUrl) أو عند إغلاق الصفحة: إما استدعاء Payment Status بالـ `merchant_reference` أو الانتظار حتى يصل الـ webhook ثم تحديث الشاشة.
- عدم تنفيذ ترقية/تخفيض خطة؛ فقط "اشتراك جديد" و"إلغاء الاشتراك".

---

## 7. مراحل التنفيذ المقترحة

| المرحلة | المهام |
|---------|--------|
| **1. التوثيق والتحقق** | تأكيد صيغ الـ signature لـ Create Session و Create Subscription و Get/Cancel. (اختياري) إضافة Fetch by Merchant Reference و Test Cards. |
| **2. DB و Models** | إضافة/تعديل الحقول والجداول (اشتراكات، محاولات دفع)، وتحديث النماذج. |
| **3. GeideaService** | تنفيذ إنشاء توقيع، Create Session، Create Subscription، Cancel، Get Subscription. |
| **4. Prepare Payment + Webhook** | دمج الخيارين (مع/بدون تجديد تلقائي)، إنشاء محاولة دفع، استقبال الـ callback والتحقق من التوقيع وتحديث البيانات وإنشاء الاشتراك المحلي. |
| **5. إلغاء الاشتراك** | ربط زر الإلغاء مع Cancel Subscription (Geidea) وتحديث الحالة محلياً. |
| **6. Flutter** | فتح HPP، تمرير `merchant_reference` وعرض الحالة بعد الدفع. |
| **7. اختبار** | Sandbox: دفعة أولى، callback، إلغاء؛ مع وبدون تجديد تلقائي. |

---

## 8. ملخص القرارات

- **ترقية/تخفيض**: غير مطبّقة.
- **اشتراك**: مدخل واحد (اختيار خطة + اختيار وجود تجديد تلقائي).
- **إلغاء**: مدخل واحد (إلغاء الاشتراك) مع استدعاء Geidea عند وجود `geidea_subscription_id`.
- **التجديد التلقائي**: إما Geidea (RecurringPayment) أو لا (دفعة واحدة فقط).

بمجرد استكمال الناقص (صيغ الـ signature واختياري Fetch + Test Cards)، يمكن تنفيذ هذه الخطة كما هي مع إضافة أو تعديلات بسيطة حسب تفاصيل الوثائق الرسمية لـ Geidea.
