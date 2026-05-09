# آلية حظر الحساب (Account ban) — Backend

يُمثَّل الحظر في قاعدة البيانات بحقل **`users.status`** من نوع enum: القيم المسموحة في الـ migration تشمل **`active`** و **`banned`** (انظر `database/migrations/..._create_users_table.php`). لا يوجد مسار API منفصل باسم «حظر»؛ التغيير يتم عبر تحديث المستخدم (لوحة الإدارة) مع إشعارات جانبية.

---

## 1. من يضع الحظر؟

- عادةً **مسار إدارة المستخدمين** (`PUT admin/users/{id}`) مع حقل `status` ضمن القيم المصرَّح بها في `UpdateUserRequest`: `active` أو `banned` فقط (`app/Http/Requests/Users/UpdateUserRequest.php`).
- عند الانتقال إلى `banned`، يستدعي **`UserService::update`** إشعار الحساب (انظر أدناه).

---

## 2. ماذا يحدث عند تغيير الحالة إلى `banned`؟

في **`App\Http\Services\Users\UserService::update`**:

- بعد ` $user->update($data) `، إذا أصبحت `($data['status'] ?? null) === 'banned'` والحالة السابقة ليست `banned`، يُستدعى **`AccountNotification::banned($userId)`**.

في **`App\Http\Notifications\AccountNotification::banned`**:

- يُرسل إشعار دفع/داخلي عبر `NotificationService::sendToUser` بنوع **`account_banned`** ونص عربي يوضح التقييد، مع `data.screen` = `support` (لاستخدام العميل في التنقل إن وُجد).

---

## 3. منع استخدام الـ API بعد الحظر (جلسة نشطة)

في **`App\Http\Middleware\SetLocaleMiddleware`** (بعد التحقق من Sanctum):

- إذا كان المستخدم مصادقاً عليه و **`$user->status === 'banned'`**:
  - يُسمح فقط بـ:
    - **`POST`** ومسار يحتوي **`auth/logout`** أو **`logout`** (تسجيل خروج وإبطال التوكن).
    - **`GET`** ومسار يحتوي **`help_center`** (قراءة محتوى مساعدة؛ في المشروع الحالي طلب إعداد **`GET .../general/settings/app.help_center`** يحتوي المسار على السلسلة `help_center` فيجتاز هذا الشرط).
  - أي طلب آخر يُرجع JSON:

```json
{
  "success": false,
  "key": "messages.user.is_banned",
  "message": "...",
  "data": null
}
```

- **رمز HTTP:** `200` (نفس أسلوب التحديث الإجباري: العميل يعتمد على `key`).

- النص من **`__('auth.account_banned')`** (`lang/ar/auth.php`, `lang/en/auth.php`).

> ملاحظة: فحص الحظر يعمل **فقط** عند وجود مستخدم مصادق (`Auth::guard('sanctum')->check()`). الطلبات بدون توكن لا تمر بهذا الفرع.

---

## 4. تسجيل الدخول لمستخدم محظور

في **`App\Http\Services\Auth\AuthService::login`** (بعد التحقق من البريد وكلمة المرور):

- إذا **`$user->status === 'banned'`** يُستدعى **`MessageService::abort(401, 'auth.account_banned')`**.

`MessageService::abort` يُرجع:

- HTTP **401**
- `key` = **`auth.account_banned`** (نفس سلسلة مفتاح الترجمة المُمرَّرة كمعامل)
- `success: false`

هذا **مختلف** عن مفتاح **`messages.user.is_banned`** الذي يُستخدم عند حظر الجلسة النشطة في الـ middleware. العميل (Flutter) يتعامل مع المفتاحين بشكل مختلف (انظر توثيق الموبايل).

---

## 5. علاقة الحظر ببقية النظام

| الموضوع | السلوك |
|--------|--------|
| توكن Sanctum | يبقى صالحاً تقنياً حتى يستدعي العميل `logout`؛ لكن كل طلب محمي يُرفض باستثناء الاستثناءات أعلاه. |
| ضيف (`is_guest`) | الحقل `status` ينطبق على المستخدم ككيان؛ يمكن نظرياً تعيين `banned` لحساب ضيف إن رُفع عبر الـ Admin. |
| تسجيل مستخدم جديد | لا يتحقق الحظر هنا إلا إن كان البريد لحساب قائم محظور يُعاد استخدامه حسب منطق التسجيل. |

---

## 6. ملخص التدفق

```
تعيين status = banned (Admin)
    → UserService::update → AccountNotification::banned → إشعار للمستخدم

طلب API + Bearer token + مستخدم banned
    → SetLocaleMiddleware
         → إن كان logout (POST) أو settings ... help_center (GET): متابعة
         → وإلا: إرجاع messages.user.is_banned وإيقاف السلسلة

محاولة login ببريد/كلمة صحيحة لحساب banned
    → AuthService::login → 401 + key auth.account_banned
```

---

## 7. ملفات مرجعية

| الملف |
|------|
| `app/Http/Middleware/SetLocaleMiddleware.php` |
| `app/Http/Services/Auth/AuthService.php` (`login`) |
| `app/Http/Services/Users/UserService.php` (`update`) |
| `app/Http/Notifications/AccountNotification.php` |
| `app/Services/MessageService.php` |
| `database/migrations/0001_01_01_000000_create_users_table.php` (عمود `status`) |
| `app/Http/Requests/Users/UpdateUserRequest.php` |
