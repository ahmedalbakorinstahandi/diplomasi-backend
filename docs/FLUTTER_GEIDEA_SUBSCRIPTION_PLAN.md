# خطة تكامل الموبايل (Flutter) — الاشتراكات والدفع عبر Geidea

## تعليمات لفريق الموبايل

- **اعتبار كل الكود الحالي** الخاص بالاشتراكات والدفع (شاشات، منطق، استدعاءات قديمة) **غير معتمد**. يفضّل عدم إعادة استخدامه والبدء من صفحة بيضاء حسب التدفق أدناه والـ API الجديدة.

---

## التدفق العام (بدون تفصيل واجهات أو كود)

1. **اختيار الخطة وبدء الدفع**
   - المستخدم يختار خطة (plan) ويرسل طلب **prepare-payment** إلى الباكند.
   - الطلب: `POST /api/v1/user/subscriptions/prepare-payment` مع body: `plan_id`, `auto_renew` (اختياري، افتراضي false).
   - الاستجابة الناجحة تحتوي: `session_id`, `merchant_reference`, `checkout_url`, `hpp_script_url`.

2. **فتح صفحة الدفع (HPP)**
   - الباكند يعيد `checkout_url` (رابط Geidea Hosted Payment Page) و`session_id`.
   - الموبايل يفتح هذا الرابط للمستخدم (مثلاً WebView أو متصفح خارجي أو In-App Browser) بحيث يُكمل المستخدم الدفع على صفحة Geidea.

3. **بعد انتهاء المستخدم من الدفع**
   - Geidea تعيد المستخدم إلى الـ `return_url` (يُحدد من الباكند) و/أو ترسل **callback** إلى الباكند.
   - الموبايل إما:
     - يعتمد على العودة إلى الـ return_url ثم يستدعي **payment-status** لمعرفة النتيجة، أو
     - ينتظر تحديث من الباكند (مثلاً عند العودة للتطبيق يعرض حالة الاشتراك بعد استدعاء الـ API المناسبة).

4. **التحقق من حالة الدفع**
   - استدعاء: `GET /api/v1/user/subscriptions/payment-status/{merchant_reference}` (بنفس الـ merchant_reference الذي تم إرجاعه من prepare-payment).
   - الاستجابة تحتوي: `status` (مثلاً completed, pending, failed), `subscription_id` إن وُجد، `verified_at`.

5. **عرض الاشتراك الحالي والاشتراكات**
   - الاشتراك الحالي: `GET /api/v1/user/subscriptions/current`.
   - قائمة اشتراكات المستخدم: `GET /api/v1/user/subscriptions`.
   - تفاصيل اشتراك: `GET /api/v1/user/subscriptions/{id}`.

6. **إلغاء التجديد التلقائي**
   - استدعاء: `POST /api/v1/user/subscriptions/{id}/cancel-auto-renew`.
   - إن الاشتراك مربوط بـ Geidea (تجديد تلقائي)، الباكند يلغي عند Geidea ثم يحدّث الحالة محلياً.

7. **الفواتير والدفعات**
   - قائمة الدفعات/المعاملات: `GET /api/v1/user/payments`.
   - عرض الفاتورة (HTML في المتصفح/WebView): `GET /api/v1/user/invoices/{transaction_id}` (مع مصادقة المستخدم).
   - تنزيل الفاتورة (PDF): `GET /api/v1/user/invoices/{transaction_id}/download` (مع مصادقة المستخدم).

---

## ملاحظات

- **المصادقة**: كل المسارات أعلاه تحت مسبقية `auth:sanctum` (مصادقة المستخدم).
- **لا ترقية/تخفيض خطة** في هذا النطاق؛ التدفق الحالي: اشتراك جديد، إلغاء تجديد تلقائي، عرض وفواتير.
- **الويب هوك**: الباكند يستقبل callback من Geidea على مسار عام (بدون مصادقة) ويحدّث حالة المحاولة والاشتراك؛ الموبايل لا يستدعي الـ webhook.

---

## ملخص الـ Endpoints المستخدمة من الموبايل

| الغرض | Method | المسار |
|--------|--------|--------|
| تجهيز الدفع والحصول على رابط الدفع | POST | `/api/v1/user/subscriptions/prepare-payment` |
| حالة الدفع حسب merchant_reference | GET | `/api/v1/user/subscriptions/payment-status/{merchantReference}` |
| الاشتراك الحالي | GET | `/api/v1/user/subscriptions/current` |
| قائمة الاشتراكات | GET | `/api/v1/user/subscriptions` |
| تفاصيل اشتراك | GET | `/api/v1/user/subscriptions/{id}` |
| إلغاء التجديد التلقائي | POST | `/api/v1/user/subscriptions/{id}/cancel-auto-renew` |
| قائمة الدفعات | GET | `/api/v1/user/payments` |
| عرض الفاتورة (HTML) | GET | `/api/v1/user/invoices/{id}` |
| تنزيل الفاتورة (PDF) | GET | `/api/v1/user/invoices/{id}/download` |
