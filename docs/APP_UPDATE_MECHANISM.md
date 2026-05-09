# آلية التحديث الإجباري والاختياري للتطبيق (Backend)

هذا المستند يشرح كيف يُنفَّذ على خادم Laravel (`diplomasi-backend`) فصل **الحد الأدنى الإجباري للإصدار** عن **الحد الأدنى المقترح للتحديث الاختياري**، والاعتماد على إعدادات `settings` وعناوين HTTP من العميل.

---

## 1. مصدر الحقيقة: جدول الإعدادات (`settings`)

تُخزَّن القيم في السجلات ذات `key_name` التالية (نموذج `App\Models\System\Setting`):

| المفتاح | الغرض |
|--------|--------|
| `app.min_version` | أقل إصدار **مسموح** للتطبيق. إذا كان إصدار العميل أقل من هذا القيمة → **تحديث إجباري** (يُرجع الـ API مفتاح `app.force_update`). |
| `app.suggested_min_version` | أقل إصدار **يُنصح** به. إذا كان إصدار العميل أقل من هذه القيمة (ولم يُجبر بعد) → **اقتراح تحديث** (`suggest: true`). |
| `app.google_play_link` | رابط متجر Google Play (يُعاد في الحمولة للأندرويد). |
| `app.apple_store_link` | رابط App Store (يُعاد في الحمولة لـ iOS). |

**ملاحظة من الـ seeder:** يجب أن يكون `app.suggested_min_version` **أقل من** `app.min_version` حتى يظهر تنبيه التحديث الاختياري للمستخدمين الذين ما زالوا فوق الحد الإجباري لكن دون النسخة المثالية. انظر `database/seeders/SettingSeeder.php`.

---

## 2. التحديث الإجباري (Force update)

### 2.1 أين يُنفَّذ؟

في **`App\Http\Middleware\SetLocaleMiddleware`**، بعد منطق اللغة والمستخدم المحظور، و**قبل** أن يصل الطلب إلى الـ Controller.

### 2.2 شروط التطبيق

يُقيَّم الحظر فقط عندما:

1. المسار **لا** يحتوي على `webhooks` (استثناء لمسارات مثل Moyasar/Apple حتى لا تُكسر الـ webhooks).
2. رأس الطلب `X-Context` (بأحرف صغيرة بعد `strtolower`) يساوي **`app`** بالضبط.

> **تفصيل مهم:** الـ middleware يقرأ `X-Context` **بدون** القيمة الافتراضية نفسها المستخدمة في `RequestContextMiddleware`. إذا لم يُرسل الرأس، تكون القيمة فارغة ولن يُفعَّل فحص التحديث الإجباري من هذا المسار — بينما `RequestContextMiddleware` قد يعتبر السياق `app` لاحقاً. عملياً، تطبيق الموبايل يرسل `X-Context: app` دائماً عبر Dio.

### 2.3 المنطق

- يُقرأ إصدار العميل من الرأس: **`X-App-Version`**، والافتراض عند الغياب: `0.0.0`.
- يُجلب `app.min_version` من الإعدادات.
- إذا وُجدت قيمة `min_version` و **`version_compare($appVersion, $minVersion, '<')`** → يُرجع JSON فوراً **دون** استدعاء `$next($request)`:

```json
{
  "success": false,
  "key": "app.force_update",
  "message": "...",
  "data": {
    "store_link_android": "...",
    "store_link_ios": "..."
  }
}
```

- **رمز HTTP:** `200` (قرار تصميمي: العميل يعتمد على `success` و`key` وليس على 4xx).

- النص المترجم: مفتاح `auth.force_update` في `lang/ar/auth.php` و `lang/en/auth.php`.

### 2.4 ترتيب الـ Middleware على `api/v1`

في `routes/api/api.php`:

`SetLocaleMiddleware` → `RequestContextMiddleware` → بقية المسارات.

أي طلب API تحت `v1` يمر أولاً بفحص التحديث الإجباري (إذا استوفى الشروط أعلاه)، بما في ذلك المسارات العامة والمحمية.

---

## 3. التحديث الاختياري (Suggest update)

لا يمنع الطلبات؛ يُعاد فقط حقل يوضح للعميل ما إذا كان يُنصح بالتحديث.

### 3.1 الخدمة المشتركة

**`App\Http\Services\System\AppUpdateSuggestService`**

- الدالة `buildForClientVersion(string $appVersion): array`
- تقرأ `app.suggested_min_version`.
- إذا كان `version_compare($appVersion, $suggestedMinVersion, '<')` → `suggest: true` وتُملأ روابط المتاجر من الإعدادات.
- وإلا → `suggest: false` وروابط المتاجر `null`.

شكل المخرجات:

```php
[
    'suggest' => bool,
    'store_link_android' => ?string,
    'store_link_ios' => ?string,
]
```

### 3.2 مسار عام مستقل

**`GET /api/v1/general/app-update-check`**

- Controller: `App\Http\Controllers\System\AppUpdateController::checkSuggest`
- يقرأ `X-App-Version` من الرأس (افتراضي `0.0.0`).
- يستدعي `AppUpdateSuggestService` ويُرجع:

```json
{
  "success": true,
  "data": {
    "suggest": true|false,
    "store_link_android": null|string,
    "store_link_ios": null|string
  }
}
```

التعريف في `routes/api/v1/api_general.php`.

### 3.3 دمج نفس المنطق في `GET /user/me`

عندما يكون الطلب من تطبيق الجوال (`RequestContext::isApp()`):

- في `UserController::getProfile`، إذا كان السياق تطبيقاً، تُدمج معاملات إضافية عبر **`UserMeAppPayloadComposer::buildForAppRequest`**.

إذا وُجدت query parameter **`include_app_update_check`** بقيمة truthy (`1`, `true`, `yes`, `on`):

- يُضاف للاستجابة الجذر (بجانب `success` و`data` وليس داخل `data.user` فقط) المفتاح **`app_update_check`** بنفس شكل `AppUpdateSuggestService`.

> الدمج يتم عبر `ResponseService::response`: أي مفاتيح إضافية في مصفوفة `$params` تُنسخ للجذر (مثل `courses_mode`, `billing_subscription`, `app_update_check`).

---

## 4. رؤوس HTTP المتوقعة من العميل

| الرأس | الاستخدام |
|--------|-----------|
| `X-App-Version` | semver من التطبيق (مثلاً من `package_info` / `pubspec`). |
| `X-Context: app` | لتمييز طلبات الموبايل عن لوحة التحكم؛ مطلوب لسلوك التحديث الإجباري في `SetLocaleMiddleware` كما هو مبرمج حالياً. |

---

## 5. إدارة القيم من لوحة التحكم

حقول `app.min_version` و `app.suggested_min_version` وروابط المتاجر مُعرَّفة في واجهة الإعدادات (مثلاً `diplomasi-dashboard` — `settings-field-registry.ts`). تغيير القيم في الإنتاج يُحدّث السلوك فوراً للطلبات التالية.

---

## 6. ملخص تدفق القرار (Backend)

```
طلب API (v1)
    → SetLocaleMiddleware
         → إن كان مسار webhooks: تخطي القوة
         → إن كان X-Context != app: تخطي القوة
         → إن كان X-App-Version < app.min_version: إرجاع app.force_update وإيقاف السلسلة
    → RequestContextMiddleware + Controllers
         → اختياري: AppUpdateSuggestService عبر app-update-check أو include_app_update_check على /user/me
```

---

## 7. ملفات مرجعية سريعة

| الملف |
|------|
| `app/Http/Middleware/SetLocaleMiddleware.php` |
| `app/Http/Services/System/AppUpdateSuggestService.php` |
| `app/Http/Controllers/System/AppUpdateController.php` |
| `app/Http/Services/Users/UserMeAppPayloadComposer.php` |
| `app/Http/Controllers/Users/UserController.php` (`getProfile`) |
| `routes/api/v1/api_general.php` |
| `database/seeders/SettingSeeder.php` |
