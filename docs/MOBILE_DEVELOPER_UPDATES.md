# دليل مطور Flutter — الاشتراكات والدفع (Geidea)

**ملف واحد مرجعي.** كل ما يلزم لتكامل الاشتراكات والدفع في تطبيق Flutter مع الباكند و Geidea.

---

## 1. نموذج الاشتراك (قرارات نهائية)

| البند | القرار |
|--------|--------|
| **التجديد** | اشتراك عادي. الاشتراك يبقى يتجدد تلقائياً ما لم يتم إلغاؤه. لا خيار للمستخدم عند الاشتراك (تفعيل/إلغاء التجديد). |
| **إلغاء الاشتراك** | لفظ الواجهة: **"إلغاء الاشتراك"**. المعنى: إيقاف التجديد التلقائي فقط. الاشتراك **الحالي** يبقى فعّالاً حتى `end_date`. |
| **بعد الإلغاء** | لا "إعادة تفعيل" ولا "استئناف". لإعادة الاشتراك: انتظار انتهاء المدة ثم الاشتراك من جديد (خطة + دفع). |

**ملخص:** اشتراك عادي، والاشتراك يبقى يتجدد ما لم يتم إلغاؤه. عند الإلغاء: إيقاف التجديد مع بقاء الفترة الحالية؛ بعد انتهاء المدة يمكن الاشتراك من جديد فقط.

---

## 2. التدفق (كيفية العمل)

### 2.1 الاشتراك

1. المستخدم يختار خطة (Plan).
2. التطبيق: `POST /api/v1/user/subscriptions/prepare-payment` مع body: `{ "plan_id": <id> }`.
3. الباكند يرجع: `session_id`, `merchant_reference`, `checkout_url`, `hpp_script_url`.
4. التطبيق يفتح الدفع: إما Geidea Flutter SDK (إن دعم جلسة بـ `session_id`) أو WebView يعرض `checkout_url`.
5. المستخدم يدفع على Geidea ثم يعود للتطبيق.
6. التطبيق: `GET /api/v1/user/subscriptions/payment-status/{merchant_reference}`.
7. إن الحالة completed: عرض الاشتراك (`subscriptions/current` أو `subscriptions/{id}`).

### 2.2 إلغاء الاشتراك

1. المستخدم يضغط "إلغاء الاشتراك".
2. التطبيق: `POST /api/v1/user/subscriptions/{id}/cancel-auto-renew`.
3. توضيح للمستخدم: الاشتراك الحالي يبقى حتى [end_date] ولن يتجدد بعدها.
4. **ممنوع** عرض "إعادة تفعيل التجديد" أو "استئناف الاشتراك".

### 2.3 بعد انتهاء الاشتراك (بعد end_date)

- عرض "الاشتراك من جديد" — نفس تدفق الاشتراك (اختيار خطة → prepare-payment → دفع).

---

## 3. الـ API (مرجع كامل)

**المصادقة:** كل الطلبات مع `auth:sanctum` (Bearer token).

| الغرض | Method | المسار |
|--------|--------|--------|
| تجهيز الدفع | POST | `/api/v1/user/subscriptions/prepare-payment` |
| حالة الدفع | GET | `/api/v1/user/subscriptions/payment-status/{merchantReference}` |
| الاشتراك الحالي | GET | `/api/v1/user/subscriptions/current` |
| قائمة الاشتراكات | GET | `/api/v1/user/subscriptions` |
| تفاصيل اشتراك | GET | `/api/v1/user/subscriptions/{id}` |
| إلغاء الاشتراك | POST | `/api/v1/user/subscriptions/{id}/cancel-auto-renew` |
| قائمة الدفعات | GET | `/api/v1/user/payments` |
| عرض الفاتورة (HTML) | GET | `/api/v1/user/invoices/{id}` |
| تنزيل الفاتورة (PDF) | GET | `/api/v1/user/invoices/{id}/download` |

### prepare-payment

- **Request:** `{ "plan_id": <int> }`. لا ترسل `auto_renew`.
- **Response (200):**  
  `session_id` — معرف الجلسة عند Geidea.  
  `merchant_reference` — لاستدعاء payment-status بعد العودة من الدفع.  
  `checkout_url` — رابط جاهز لصفحة الدفع (HPP)، بصيغة `.../hpp/checkout/?<session_id>`.  
  `hpp_script_url` — (اختياري) لاستخدام سكربت HPP في ويب.
- **Response (422):** `error` في الجسم عند فشل إنشاء الجلسة.

**ماذا تفعل بالاستجابة (بدون لخبطة):**
- عند Geidea **لا يوجد** كائن اسمه "Payment Intent" مثل Stripe. الدفع يتم عبر **جلسة (Session)** ورابط جاهز لصفحة الدفع.
- `checkout_url` = **رابط صفحة الدفع نفسه**. المستخدم يفتح هذا الرابط (WebView أو متصفح)، يُدخل البطاقة ويكمل الدفع، ثم يعود للتطبيق. بعد العودة استدعِ `GET payment-status/{merchant_reference}` لمعرفة إن اكتمل الدفع وحصل اشتراك.
- الـ JSON الكبير (subscription من Geidea) يصل **للباكند فقط** عند إنشاء الاشتراك؛ التطبيق لا يستلمه ولا يحتاجه. التطبيق يحتاج فقط: افتح `checkout_url` → بعد الدفع استدعِ payment-status.

**فتح الدفع بدون API password:** افتح `checkout_url` في WebView — لا حاجة لأي credential. إن دعم الـ SDK فتح جلسة بـ `session_id` فقط فاستخدمه؛ وإلا WebView مع checkout_url كافي.

**إذا صفحة الدفع فاضية (بيضاء):** 1) الجلسة تنتهي بعد 15 دقيقة — استدعِ prepare-payment من جديد وافتح الـ checkout_url الجديد فوراً. 2) تأكد أن الباكند يستخدم نفس بيئة Geidea (KSA / Egypt / UAE)؛ رابط HPP يُبنى من إعداد الباكند. 3) إن لزم، يمكن تعيين `GEIDEA_HPP_BASE_URL` في الباكند (مثلاً UAE: `https://payments.geidea.ae`).

### payment-status

- **Response:** `status` (completed | pending | failed), `subscription_id` إن وُجد، `verified_at`.

---

## 4. تكامل Flutter مع Geidea

### 4.1 المتطلبات (حسب Geidea)

| البرنامج | الإصدار |
|----------|----------|
| Android | 6.0.2+ |
| Dart | 2.18.0+ (< 4.0.0) |
| Geidea Flutter SDK | 5.0.0 |
| Flutter | 3.3.0+ |

### 4.2 إضافة الـ SDK

`pubspec.yaml`:

```yaml
dependencies:
  geideapay:
    git:
      url: https://github.com/GeideaSolutions/Flutter-SDK
      ref: main
```

ثم: `flutter pub get`.

```dart
import 'package:geideapay/geideapay.dart';
import 'package:geideapay/models/address.dart';
import 'package:geideapay/widgets/checkout/checkout_options.dart';
```

### 4.3 التهيئة وعدم تخزين API password

- **مطلوب من الباكند للموبايل:** استدعاء prepare-payment يعيد `session_id`, `merchant_reference`, `checkout_url`. الباكند أنشأ الجلسة عند Geidea؛ التطبيق **لا يحتاج** ولا **يخزّن** API password.
- إن استخدمت Geidea Flutter SDK: وثائق Geidea تذكر `GeideapayPlugin.initialize(publicKey, apiPassword)`. للالتزام بعدم وضع الـ password في التطبيق: إما استخدام **WebView + checkout_url** (مضمون بدون credentials)، أو التحقق من أن الـ SDK يدعم فتح جلسة موجودة بـ **session_id فقط** — إن وُجدت هذه الإمكانية فمرّر `session_id` من استجابة prepare-payment ولا تمرّر الـ password.

### 4.4 فتح شاشة الدفع (بدون password في التطبيق)

- **الطريقة المضمونة:** افتح `checkout_url` من استجابة prepare-payment في **WebView**. الرابط جاهز ويحتوي session_id؛ لا حاجة لأي credential. بعد عودة المستخدم استدعِ payment-status بالـ `merchant_reference`.
- **إن دعم الـ SDK فتح جلسة بـ session_id فقط:** استخدم `session_id` من الاستجابة وافتح الدفع عبر الـ SDK دون تمرير API password.

### 4.5 مثال CheckoutOptions (مرجع)

القيم الفعلية للمبلغ والعملة والـ URLs تُعرّف في الباكند عند إنشاء الجلسة. إن استخدمت الـ SDK بجلسة جاهزة فاستخدم `session_id`. مثال توضيحي من وثائق Geidea:

```dart
CheckoutOptions checkoutOptions = CheckoutOptions(
  "123.45",
  "SAR",
  callbackUrl: "https://website.hook/",
  returnUrl: "https://returnurl.com",
  lang: "AR",
  merchantReferenceID: merchantReference,
);

OrderApiResponse response = await _plugin.checkout(context: context, checkoutOptions: checkoutOptions);
```

بعد العودة: `GET /api/v1/user/subscriptions/payment-status/{merchant_reference}`.

### 4.6 عينة كود Geidea

[https://github.com/GeideaSolutions/Flutter-SDK-Sample-App/tree/MeezaQR](https://github.com/GeideaSolutions/Flutter-SDK-Sample-App/tree/MeezaQR)

---

## 5. قواعد ملزمة للمطور

1. **الكود القديم** للاشتراك/الدفع **غير معتمد.** البناء على هذا التدفق والـ API فقط.
2. **الاشتراك:** prepare-payment بـ `plan_id` فقط → فتح الدفع (SDK أو WebView) → payment-status → عرض الاشتراك.
3. **الواجهة:** "إلغاء الاشتراك" مع توضيح بقاء الفترة حتى end_date. **ممنوع** "إعادة تفعيل التجديد". بعد انتهاء الاشتراك: "الاشتراك من جديد" فقط.
4. **الويب هوك:** الباكند يستقبله من Geidea. التطبيق لا يستدعي أي webhook.
5. **resume-auto-renew:** الـ endpoint موجود في الباكند ولا يُستخدم من التطبيق. لا تعرضه للمستخدم.
