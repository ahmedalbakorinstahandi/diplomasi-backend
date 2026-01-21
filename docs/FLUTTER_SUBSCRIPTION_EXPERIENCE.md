# دليل تجربة الاشتراكات الكاملة لتطبيق Flutter - Diplomasi

## نظرة عامة

هذا الدليل الشامل يشرح بالتفصيل كيفية بناء تجربة مستخدم متكاملة لنظام الاشتراكات في تطبيق Flutter. يتضمن تصميم الصفحات، تدفقات العمل، وأمثلة كود عملية.

## المحتويات

1. [نظرة عامة على النظام](#نظرة-عامة-على-النظام)
2. [الصفحات المطلوبة](#الصفحات-المطلوبة)
3. [API Integration](#api-integration)
4. [تدفقات العمل](#تدفقات-العمل)
5. [أمثلة كود Flutter](#أمثلة-كود-flutter)
6. [UI/UX Guidelines](#uiux-guidelines)
7. [معالجة الأخطاء](#معالجة-الأخطاء)
8. [Navigation Flow](#navigation-flow)

---

## نظرة عامة على النظام

### البنية العامة

```
┌─────────────────────────────────────────────────────────┐
│                    Flutter App                          │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │
│  │ Plans Page   │  │ Subscription │  │  Payment     │ │
│  │              │  │  Details     │  │  History     │ │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘ │
│         │                  │                  │         │
│         └──────────────────┼──────────────────┘         │
│                            │                            │
│                   ┌────────▼────────┐                  │
│                   │   API Service   │                  │
│                   └────────┬────────┘                  │
│                            │                            │
└────────────────────────────┼────────────────────────────┘
                             │
                   ┌─────────▼─────────┐
                   │  Diplomasi Backend│
                   │  + Stripe API     │
                   └───────────────────┘
```

### تدفق البيانات الأساسي

```
1. المستخدم يفتح صفحة الباقات
   ↓
2. Flutter يستدعي GET /api/v1/user/plans
   ↓
3. Flutter يستدعي GET /api/v1/user/subscriptions/current
   ↓
4. Flutter يعرض الخطط مع تحديد الخططة الحالية
   ↓
5. المستخدم يختار إجراء (اشترك/ترقية/إدارة)
   ↓
6. Flutter يستدعي API المناسب
   ↓
7. تحديث UI بناءً على النتيجة
```

---

## الصفحات المطلوبة

### 1. صفحة الباقات (Plans Page)

#### الوصف
الصفحة الرئيسية لعرض جميع الخطط المتاحة. يجب أن تتعامل مع حالتين:
- **المستخدم غير مشترك**: عرض جميع الخطط مع زر "اشترك"
- **المستخدم مشترك**: تحديد الخططة الحالية وإظهار خيارات الترقية

#### التصميم المقترح

```
┌─────────────────────────────────────────────┐
│         صفحة الباقات                        │
├─────────────────────────────────────────────┤
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │  ✓ خطتك الحالية                       │ │
│  │  ┌─────────────────────────────────┐ │ │
│  │  │  Basic Plan                     │ │ │
│  │  │  $9.99/شهر                      │ │ │
│  │  │  • ميزة 1                       │ │ │
│  │  │  • ميزة 2                       │ │ │
│  │  │  [إدارة الاشتراك]               │ │ │
│  │  └─────────────────────────────────┘ │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │  Pro Plan                             │ │
│  │  $19.99/شهر                           │ │
│  │  • ميزة 1                             │ │
│  │  • ميزة 2                             │ │
│  │  • ميزة 3                             │ │
│  │  [ترقية]                              │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │  Premium Plan                         │ │
│  │  $29.99/شهر                           │ │
│  │  • جميع الميزات                       │ │
│  │  [ترقية]                              │ │
│  └───────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
```

#### البيانات المطلوبة

**API Calls:**
1. `GET /api/v1/user/plans` - جلب جميع الخطط
2. `GET /api/v1/user/subscriptions/current` - جلب الاشتراك الحالي

**Response Example:**
```json
// GET /api/v1/user/plans
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Basic",
      "price": "9.99",
      "interval": "monthly",
      "description": "وصف الخطة",
      "features": ["ميزة 1", "ميزة 2"],
      "stripe_plan_id": "plan_basic_monthly"
    }
  ]
}

// GET /api/v1/user/subscriptions/current
{
  "success": true,
  "data": {
    "id": 25,
    "plan_id": 1,
    "status": "active",
    "start_date": "2026-01-21",
    "end_date": "2026-02-20",
    "auto_renew": true
  }
}
```

#### Logic المطلوب

```dart
// تحديد إذا كانت الخطة هي الخططة الحالية
bool isCurrentPlan(Plan plan) {
  return currentSubscription?.planId == plan.id;
}

// تحديد إذا كان يمكن الترقية
bool canUpgrade(Plan plan) {
  if (currentSubscription == null) return false;
  // يمكن الترقية إذا كانت الخطة أعلى (حسب السعر)
  return plan.price > currentSubscription!.price;
}

// تحديد الأزرار المطلوبة
Widget getActionButton(Plan plan) {
  if (isCurrentPlan(plan)) {
    return ElevatedButton(
      onPressed: () => navigateToManage(),
      child: Text('إدارة الاشتراك'),
    );
  } else if (canUpgrade(plan)) {
    return ElevatedButton(
      onPressed: () => handleUpgrade(plan),
      child: Text('ترقية'),
    );
  } else {
    return ElevatedButton(
      onPressed: () => handleSubscribe(plan),
      child: Text('اشترك'),
    );
  }
}
```

#### States

- **Loading**: عرض loading indicator
- **Success**: عرض الخطط
- **Error**: عرض رسالة خطأ مع إمكانية إعادة المحاولة

---

### 2. صفحة تفاصيل الاشتراك (Subscription Details Page)

#### الوصف
صفحة تعرض تفاصيل الاشتراك الحالي مع خيارات الإدارة.

#### التصميم المقترح

```
┌─────────────────────────────────────────────┐
│  ← رجوع        تفاصيل الاشتراك              │
├─────────────────────────────────────────────┤
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │  Basic Plan                           │ │
│  │  $9.99/شهر                            │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  حالة الاشتراك: نشط                         │
│  تاريخ البدء: 21 يناير 2026                │
│  تاريخ الانتهاء: 20 فبراير 2026            │
│  التجديد التلقائي: مفعّل                   │
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │  [إلغاء التجديد التلقائي]            │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │  [ترقية الاشتراك]                    │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  معلومات الدفع:                            │
│  • آخر دفع: $9.99 - 21 يناير 2026         │
│  • طريقة الدفع: Visa •••• 4242            │
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │  [عرض تاريخ الاشتراكات]              │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │  [عرض تاريخ الدفعات]                 │ │
│  └───────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
```

#### البيانات المطلوبة

**API Call:**
- `GET /api/v1/user/subscriptions/current` - جلب الاشتراك الحالي

**Response Example:**
```json
{
  "success": true,
  "data": {
    "id": 25,
    "user_id": 3,
    "plan_id": 1,
    "plan": {
      "id": 1,
      "name": "Basic",
      "price": "9.99",
      "interval": "monthly"
    },
    "start_date": "2026-01-21T00:00:00.000000Z",
    "end_date": "2026-02-20T00:00:00.000000Z",
    "status": "active",
    "price": "9.99",
    "currency": "USD",
    "auto_renew": true,
    "stripe_subscription_id": "sub_1SrpT24OA3vmxhuF0KDIzdPk"
  }
}
```

#### Actions المتاحة

1. **إلغاء التجديد التلقائي**
   - API: `POST /api/v1/user/subscriptions/{id}/cancel-auto-renew`
   - يجب عرض تأكيد قبل التنفيذ

2. **استئناف التجديد التلقائي**
   - API: `POST /api/v1/user/subscriptions/{id}/resume-auto-renew`

3. **ترقية الاشتراك**
   - الانتقال لصفحة الباقات مع تحديد خطة أعلى

4. **عرض تاريخ الاشتراكات**
   - الانتقال لصفحة تاريخ الاشتراكات

5. **عرض تاريخ الدفعات**
   - الانتقال لصفحة تاريخ الدفعات

---

### 3. صفحة تاريخ الاشتراكات (Subscription History Page)

#### الوصف
صفحة تعرض جميع الاشتراكات السابقة والحالية للمستخدم.

#### التصميم المقترح

```
┌─────────────────────────────────────────────┐
│  ← رجوع        تاريخ الاشتراكات             │
├─────────────────────────────────────────────┤
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │  Basic Plan                           │ │
│  │  حالة: نشط                            │ │
│  │  من: 21 يناير 2026                   │ │
│  │  إلى: 20 فبراير 2026                 │ │
│  │  [عرض التفاصيل]                      │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │  Pro Plan                             │ │
│  │  حالة: منتهي                          │ │
│  │  من: 1 ديسمبر 2025                   │ │
│  │  إلى: 31 ديسمبر 2025                 │ │
│  │  [عرض التفاصيل]                      │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │  Basic Plan                           │ │
│  │  حالة: ملغى                           │ │
│  │  من: 1 نوفمبر 2025                   │ │
│  │  إلى: 30 نوفمبر 2025                 │ │
│  │  [عرض التفاصيل]                      │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  [تحميل المزيد...]                         │
└─────────────────────────────────────────────┘
```

#### البيانات المطلوبة

**API Call:**
- `GET /api/v1/user/subscriptions?page=1&per_page=20&status=active,expired,cancelled`

**Query Parameters:**
- `page`: رقم الصفحة (افتراضي: 1)
- `per_page`: عدد العناصر في الصفحة (افتراضي: 20)
- `status`: تصفية حسب الحالة (active, expired, cancelled)

**Response Example:**
```json
{
  "success": true,
  "data": [
    {
      "id": 25,
      "plan_id": 1,
      "plan": {
        "id": 1,
        "name": "Basic",
        "price": "9.99"
      },
      "start_date": "2026-01-21T00:00:00.000000Z",
      "end_date": "2026-02-20T00:00:00.000000Z",
      "status": "active",
      "price": "9.99",
      "currency": "USD",
      "auto_renew": true
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 5,
    "last_page": 1
  }
}
```

#### Features

- **Pagination**: تحميل المزيد عند التمرير
- **Filtering**: تصفية حسب الحالة
- **Details**: الضغط على اشتراك للانتقال لصفحة التفاصيل

---

### 4. صفحة تاريخ الدفعات (Payment History Page)

#### الوصف
صفحة تعرض جميع المعاملات المالية للمستخدم (دفعات، ترقيات، استردادات).

#### التصميم المقترح

```
┌─────────────────────────────────────────────┐
│  ← رجوع        تاريخ الدفعات                │
├─────────────────────────────────────────────┤
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │  💳 دفع اشتراك                        │ │
│  │  Basic Plan                           │ │
│  │  $9.99                                │ │
│  │  ✅ مكتمل                             │ │
│  │  21 يناير 2026                       │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │  ⬆️ ترقية اشتراك                      │ │
│  │  Basic → Pro                          │ │
│  │  $10.00                               │ │
│  │  ✅ مكتمل                             │ │
│  │  15 يناير 2026                       │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │  💰 استرداد                           │ │
│  │  Basic Plan                           │ │
│  │  -$9.99                               │ │
│  │  ✅ مكتمل                             │ │
│  │  10 يناير 2026                       │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  [تحميل المزيد...]                         │
└─────────────────────────────────────────────┘
```

#### البيانات المطلوبة

**API Call:**
- `GET /api/v1/user/payments?page=1&per_page=20&type=subscription_payment,upgrade_payment,refund`

**Query Parameters:**
- `page`: رقم الصفحة (افتراضي: 1)
- `per_page`: عدد العناصر في الصفحة (افتراضي: 20)
- `type`: تصفية حسب النوع (subscription_payment, upgrade_payment, refund)
- `status`: تصفية حسب الحالة (completed, pending, failed)
- `start_date`: تاريخ البداية (YYYY-MM-DD)
- `end_date`: تاريخ النهاية (YYYY-MM-DD)

**Response Example:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "subscription_id": 25,
      "type": "subscription_payment",
      "amount": "9.99",
      "currency": "USD",
      "status": "completed",
      "description": "Subscription payment for plan: Basic",
      "processed_at": "2026-01-21T00:40:18.000000Z",
      "created_at": "2026-01-21T00:40:18.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 10,
    "last_page": 1
  }
}
```

#### Types of Transactions

- **subscription_payment**: دفع اشتراك جديد أو تجديد
- **upgrade_payment**: دفع ترقية
- **refund**: استرداد

#### Status Values

- **completed**: مكتمل
- **pending**: معلق
- **failed**: فاشل

---

### 5. صفحة إدارة الاشتراك (Manage Subscription Page)

#### الوصف
صفحة مركزية لإدارة جميع جوانب الاشتراك.

#### التصميم المقترح

```
┌─────────────────────────────────────────────┐
│  ← رجوع        إدارة الاشتراك                │
├─────────────────────────────────────────────┤
│                                             │
│  معلومات الاشتراك:                          │
│  ┌───────────────────────────────────────┐ │
│  │  Basic Plan - $9.99/شهر              │ │
│  │  نشط حتى: 20 فبراير 2026            │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  التجديد التلقائي:                         │
│  ┌───────────────────────────────────────┐ │
│  │  ✓ مفعّل                              │ │
│  │  [إلغاء التجديد التلقائي]            │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  الإجراءات:                                 │
│  ┌───────────────────────────────────────┐ │
│  │  [ترقية الاشتراك]                    │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  البطاقات المحفوظة:                         │
│  ┌───────────────────────────────────────┐ │
│  │  Visa •••• 4242                      │ │
│  │  ✓ افتراضي                           │ │
│  │  [تغيير]                              │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  روابط سريعة:                               │
│  • [عرض تاريخ الاشتراكات]                 │
│  • [عرض تاريخ الدفعات]                    │
└─────────────────────────────────────────────┘
```

---

## API Integration

### Endpoints الجديدة

#### 1. GET /api/v1/user/subscriptions

**الوصف:** جلب قائمة جميع الاشتراكات للمستخدم

**Headers:**
```
Authorization: Bearer {token}
Accept-Language: ar
```

**Query Parameters:**
- `page` (optional): رقم الصفحة (افتراضي: 1)
- `per_page` (optional): عدد العناصر (افتراضي: 20)
- `status` (optional): تصفية حسب الحالة (active, expired, cancelled)
- `sort_field` (optional): حقل الترتيب (افتراضي: created_at)
- `sort_order` (optional): اتجاه الترتيب (asc, desc) (افتراضي: desc)

**cURL Example:**
```bash
curl -X GET "https://diplomasi-backend.ahmed-albakor.com/api/v1/user/subscriptions?page=1&per_page=20&status=active,expired" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept-Language: ar"
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 25,
      "user_id": 3,
      "plan_id": 1,
      "plan": {
        "id": 1,
        "name": "Basic",
        "price": "9.99",
        "interval": "monthly"
      },
      "start_date": "2026-01-21T00:00:00.000000Z",
      "end_date": "2026-02-20T00:00:00.000000Z",
      "status": "active",
      "price": "9.99",
      "currency": "USD",
      "auto_renew": true,
      "created_at": "2026-01-21T00:40:18.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 5,
    "last_page": 1
  }
}
```

---

#### 2. GET /api/v1/user/subscriptions/{id}

**الوصف:** جلب تفاصيل اشتراك محدد للمستخدم

**Headers:**
```
Authorization: Bearer {token}
Accept-Language: ar
```

**cURL Example:**
```bash
curl -X GET "https://diplomasi-backend.ahmed-albakor.com/api/v1/user/subscriptions/25" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept-Language: ar"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 25,
    "user_id": 3,
    "plan_id": 1,
    "plan": {
      "id": 1,
      "name": "Basic",
      "price": "9.99",
      "interval": "monthly"
    },
    "start_date": "2026-01-21T00:00:00.000000Z",
    "end_date": "2026-02-20T00:00:00.000000Z",
    "status": "active",
    "price": "9.99",
    "currency": "USD",
    "auto_renew": true,
    "subscription_events": [
      {
        "id": 1,
        "event_type": "created",
        "created_at": "2026-01-21T00:40:18.000000Z"
      }
    ],
    "created_at": "2026-01-21T00:40:18.000000Z"
  }
}
```

---

#### 3. GET /api/v1/user/payments

**الوصف:** جلب تاريخ الدفعات/المعاملات المالية للمستخدم

**Headers:**
```
Authorization: Bearer {token}
Accept-Language: ar
```

**Query Parameters:**
- `page` (optional): رقم الصفحة (افتراضي: 1)
- `per_page` (optional): عدد العناصر (افتراضي: 20)
- `type` (optional): تصفية حسب النوع (subscription_payment, upgrade_payment, refund)
- `status` (optional): تصفية حسب الحالة (completed, pending, failed)
- `start_date` (optional): تاريخ البداية (YYYY-MM-DD)
- `end_date` (optional): تاريخ النهاية (YYYY-MM-DD)
- `sort_field` (optional): حقل الترتيب (افتراضي: created_at)
- `sort_order` (optional): اتجاه الترتيب (asc, desc) (افتراضي: desc)

**cURL Example:**
```bash
curl -X GET "https://diplomasi-backend.ahmed-albakor.com/api/v1/user/payments?page=1&per_page=20&type=subscription_payment,upgrade_payment" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept-Language: ar"
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "subscription_id": 25,
      "type": "subscription_payment",
      "amount": "9.99",
      "currency": "USD",
      "status": "completed",
      "description": "Subscription payment for plan: Basic",
      "stripe_payment_intent_id": "pi_3SrpT24OA3vmxhuF0KDIzdPk",
      "processed_at": "2026-01-21T00:40:18.000000Z",
      "created_at": "2026-01-21T00:40:18.000000Z",
      "subscription": {
        "id": 25,
        "plan": {
          "name": "Basic"
        }
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 10,
    "last_page": 1
  }
}
```

---

### Endpoints الموجودة (مرجع سريع)

| Endpoint | Method | الوصف |
|----------|--------|-------|
| `/api/v1/user/plans` | GET | جلب جميع الخطط |
| `/api/v1/user/subscriptions/current` | GET | جلب الاشتراك الحالي |
| `/api/v1/user/subscriptions/prepare-payment` | POST | إعداد الدفع |
| `/api/v1/user/subscriptions` | POST | إنشاء اشتراك |
| `/api/v1/user/subscriptions/{id}/cancel-auto-renew` | POST | إلغاء التجديد التلقائي |
| `/api/v1/user/subscriptions/{id}/resume-auto-renew` | POST | استئناف التجديد التلقائي |
| `/api/v1/user/subscriptions/{id}/upgrade` | POST | ترقية الاشتراك |

---

## تدفقات العمل

### تدفق 1: عرض صفحة الباقات (مشترك)

```
1. المستخدم يفتح صفحة الباقات
   ↓
2. Flutter يستدعي GET /api/v1/user/plans
   ↓
3. Flutter يستدعي GET /api/v1/user/subscriptions/current
   ↓
4. Flutter يقارن plan_id من كل خطة مع plan_id من الاشتراك الحالي
   ↓
5. Flutter يعرض الخطط مع:
   - Badge "خطتك الحالية" على الخططة الحالية
   - زر "إدارة الاشتراك" على الخططة الحالية
   - زر "ترقية" على الخطط الأعلى
   - زر "اشترك" على الخطط الأخرى
   ↓
6. المستخدم يمكنه:
   - الضغط على "إدارة الاشتراك" → الانتقال لصفحة الإدارة
   - الضغط على "ترقية" → بدء تدفق الترقية
   - الضغط على "اشترك" → بدء تدفق الاشتراك
```

### تدفق 2: ترقية الاشتراك

```
1. المستخدم يضغط "ترقية" على خطة أعلى
   ↓
2. Flutter يعرض تأكيد مع:
   - السعر الجديد
   - المبلغ المطلوب (بعد خصم القيمة المتبقية)
   - تاريخ التفعيل
   ↓
3. المستخدم يؤكد
   ↓
4. Flutter يستدعي POST /api/v1/user/subscriptions/prepare-payment
   (مع plan_id الجديد)
   ↓
5. Flutter يعرض Stripe Payment Sheet
   ↓
6. المستخدم يدفع
   ↓
7. بعد نجاح الدفع، Flutter يستدعي:
   POST /api/v1/user/subscriptions/{id}/upgrade
   (مع plan_id الجديد)
   ↓
8. Flutter يعرض رسالة نجاح
   ↓
9. Flutter يحدث UI ويعيد تحميل البيانات
```

### تدفق 3: عرض تاريخ الاشتراكات

```
1. المستخدم يضغط "عرض تاريخ الاشتراكات"
   ↓
2. Flutter يستدعي GET /api/v1/user/subscriptions?page=1
   ↓
3. Flutter يعرض القائمة مع pagination
   ↓
4. المستخدم يمكنه:
   - التمرير لتحميل المزيد (pagination)
   - الضغط على اشتراك → الانتقال لصفحة التفاصيل
   - تصفية حسب الحالة (اختياري)
```

### تدفق 4: عرض تاريخ الدفعات

```
1. المستخدم يضغط "عرض تاريخ الدفعات"
   ↓
2. Flutter يستدعي GET /api/v1/user/payments?page=1
   ↓
3. Flutter يعرض القائمة مع:
   - نوع المعاملة (أيقونة + نص)
   - المبلغ
   - الحالة (لون + نص)
   - التاريخ
   ↓
4. المستخدم يمكنه:
   - التمرير لتحميل المزيد (pagination)
   - تصفية حسب النوع (اختياري)
   - تصفية حسب الحالة (اختياري)
   - تصفية حسب التاريخ (اختياري)
```

### تدفق 5: إلغاء التجديد التلقائي

```
1. المستخدم يضغط "إلغاء التجديد التلقائي"
   ↓
2. Flutter يعرض تأكيد:
   "هل أنت متأكد من إلغاء التجديد التلقائي؟
    الاشتراك سيستمر حتى [تاريخ الانتهاء]"
   ↓
3. المستخدم يؤكد
   ↓
4. Flutter يستدعي POST /api/v1/user/subscriptions/{id}/cancel-auto-renew
   ↓
5. Flutter يعرض رسالة نجاح
   ↓
6. Flutter يحدث UI (تغيير الزر إلى "استئناف التجديد التلقائي")
```

---

## أمثلة كود Flutter

### مثال 1: صفحة الباقات الكاملة

```dart
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class PlansPage extends StatefulWidget {
  @override
  _PlansPageState createState() => _PlansPageState();
}

class _PlansPageState extends State<PlansPage> {
  List<Plan> plans = [];
  Subscription? currentSubscription;
  bool isLoading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    loadData();
  }

  Future<void> loadData() async {
    try {
      setState(() {
        isLoading = true;
        error = null;
      });

      // 1. جلب الخطط
      final plansResponse = await http.get(
        Uri.parse('$baseUrl/api/v1/user/plans'),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept-Language': 'ar',
        },
      );

      if (plansResponse.statusCode != 200) {
        throw Exception('Failed to load plans');
      }

      final plansData = json.decode(plansResponse.body);
      final plansList = (plansData['data'] as List)
          .map((p) => Plan.fromJson(p))
          .toList();

      // 2. جلب الاشتراك الحالي
      Subscription? current;
      try {
        final currentResponse = await http.get(
          Uri.parse('$baseUrl/api/v1/user/subscriptions/current'),
          headers: {
            'Authorization': 'Bearer $token',
            'Accept-Language': 'ar',
          },
        );

        if (currentResponse.statusCode == 200) {
          final currentData = json.decode(currentResponse.body);
          if (currentData['data'] != null) {
            current = Subscription.fromJson(currentData['data']);
          }
        }
      } catch (e) {
        // لا يوجد اشتراك حالياً
      }

      setState(() {
        plans = plansList;
        currentSubscription = current;
        isLoading = false;
      });
    } catch (e) {
      setState(() {
        error = e.toString();
        isLoading = false;
      });
    }
  }

  bool isCurrentPlan(Plan plan) {
    return currentSubscription?.planId == plan.id;
  }

  bool canUpgrade(Plan plan) {
    if (currentSubscription == null) return false;
    return plan.price > currentSubscription!.price;
  }

  @override
  Widget build(BuildContext context) {
    if (isLoading) {
      return Scaffold(
        appBar: AppBar(title: Text('الباقات')),
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (error != null) {
      return Scaffold(
        appBar: AppBar(title: Text('الباقات')),
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text('حدث خطأ: $error'),
              ElevatedButton(
                onPressed: loadData,
                child: Text('إعادة المحاولة'),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: Text('الباقات')),
      body: ListView.builder(
        padding: EdgeInsets.all(16),
        itemCount: plans.length,
        itemBuilder: (context, index) {
          final plan = plans[index];
          final isCurrent = isCurrentPlan(plan);
          final canUpgradeThis = canUpgrade(plan);

          return PlanCard(
            plan: plan,
            isCurrentPlan: isCurrent,
            showSubscribeButton: !isCurrent && currentSubscription == null,
            showUpgradeButton: canUpgradeThis && !isCurrent,
            showManageButton: isCurrent,
            onSubscribe: () => _handleSubscribe(plan),
            onUpgrade: () => _handleUpgrade(plan),
            onManage: () => _navigateToManage(),
          );
        },
      ),
    );
  }

  void _handleSubscribe(Plan plan) {
    // Navigate to subscribe flow
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => SubscribeFlowPage(plan: plan),
      ),
    );
  }

  void _handleUpgrade(Plan plan) {
    // Navigate to upgrade flow
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => UpgradeFlowPage(
          currentSubscription: currentSubscription!,
          newPlan: plan,
        ),
      ),
    );
  }

  void _navigateToManage() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => ManageSubscriptionPage(
          subscription: currentSubscription!,
        ),
      ),
    );
  }
}
```

### مثال 2: PlanCard Widget

```dart
class PlanCard extends StatelessWidget {
  final Plan plan;
  final bool isCurrentPlan;
  final bool showSubscribeButton;
  final bool showUpgradeButton;
  final bool showManageButton;
  final VoidCallback onSubscribe;
  final VoidCallback onUpgrade;
  final VoidCallback onManage;

  const PlanCard({
    Key? key,
    required this.plan,
    required this.isCurrentPlan,
    required this.showSubscribeButton,
    required this.showUpgradeButton,
    required this.showManageButton,
    required this.onSubscribe,
    required this.onUpgrade,
    required this.onManage,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.only(bottom: 16),
      elevation: isCurrentPlan ? 4 : 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: isCurrentPlan
            ? BorderSide(color: Colors.green, width: 2)
            : BorderSide.none,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Badge للخطة الحالية
          if (isCurrentPlan)
            Container(
              padding: EdgeInsets.symmetric(vertical: 8, horizontal: 16),
              decoration: BoxDecoration(
                color: Colors.green[100],
                borderRadius: BorderRadius.only(
                  topLeft: Radius.circular(12),
                  topRight: Radius.circular(12),
                ),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.check_circle, color: Colors.green, size: 20),
                  SizedBox(width: 8),
                  Text(
                    'خطتك الحالية',
                    style: TextStyle(
                      color: Colors.green[900],
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),

          // معلومات الخطة
          Padding(
            padding: EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  plan.name,
                  style: TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                SizedBox(height: 8),
                Text(
                  '\$${plan.price.toStringAsFixed(2)}/${_getIntervalText(plan.interval)}',
                  style: TextStyle(
                    fontSize: 20,
                    color: Colors.blue,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                if (plan.description != null) ...[
                  SizedBox(height: 8),
                  Text(
                    plan.description!,
                    style: TextStyle(color: Colors.grey[600]),
                  ),
                ],
                if (plan.features != null && plan.features!.isNotEmpty) ...[
                  SizedBox(height: 16),
                  ...plan.features!.map((feature) => Padding(
                        padding: EdgeInsets.only(bottom: 4),
                        child: Row(
                          children: [
                            Icon(Icons.check, color: Colors.green, size: 16),
                            SizedBox(width: 8),
                            Expanded(child: Text(feature)),
                          ],
                        ),
                      )),
                ],
              ],
            ),
          ),

          // الأزرار
          Padding(
            padding: EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (showSubscribeButton)
                  ElevatedButton(
                    onPressed: onSubscribe,
                    style: ElevatedButton.styleFrom(
                      padding: EdgeInsets.symmetric(vertical: 16),
                    ),
                    child: Text('اشترك'),
                  ),
                if (showUpgradeButton)
                  ElevatedButton(
                    onPressed: onUpgrade,
                    style: ElevatedButton.styleFrom(
                      padding: EdgeInsets.symmetric(vertical: 16),
                      backgroundColor: Colors.orange,
                    ),
                    child: Text('ترقية'),
                  ),
                if (showManageButton)
                  OutlinedButton(
                    onPressed: onManage,
                    style: OutlinedButton.styleFrom(
                      padding: EdgeInsets.symmetric(vertical: 16),
                    ),
                    child: Text('إدارة الاشتراك'),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _getIntervalText(String interval) {
    switch (interval) {
      case 'monthly':
        return 'شهر';
      case 'semi_annual':
        return '6 أشهر';
      case 'annual':
        return 'سنة';
      default:
        return interval;
    }
  }
}
```

### مثال 3: صفحة تاريخ الاشتراكات

```dart
class SubscriptionHistoryPage extends StatefulWidget {
  @override
  _SubscriptionHistoryPageState createState() => _SubscriptionHistoryPageState();
}

class _SubscriptionHistoryPageState extends State<SubscriptionHistoryPage> {
  List<Subscription> subscriptions = [];
  bool isLoading = true;
  bool hasMore = true;
  int currentPage = 1;
  String? selectedStatus;

  @override
  void initState() {
    super.initState();
    loadSubscriptions();
  }

  Future<void> loadSubscriptions({bool loadMore = false}) async {
    if (!loadMore) {
      setState(() {
        isLoading = true;
        currentPage = 1;
        subscriptions = [];
      });
    }

    try {
      final queryParams = {
        'page': currentPage.toString(),
        'per_page': '20',
        'sort_field': 'created_at',
        'sort_order': 'desc',
      };

      if (selectedStatus != null) {
        queryParams['status'] = selectedStatus!;
      }

      final uri = Uri.parse('$baseUrl/api/v1/user/subscriptions')
          .replace(queryParameters: queryParams);

      final response = await http.get(
        uri,
        headers: {
          'Authorization': 'Bearer $token',
          'Accept-Language': 'ar',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        final newSubscriptions = (data['data'] as List)
            .map((s) => Subscription.fromJson(s))
            .toList();

        final meta = data['meta'];
        final hasMoreData = meta['current_page'] < meta['last_page'];

        setState(() {
          if (loadMore) {
            subscriptions.addAll(newSubscriptions);
          } else {
            subscriptions = newSubscriptions;
          }
          hasMore = hasMoreData;
          currentPage = meta['current_page'] + 1;
          isLoading = false;
        });
      } else {
        throw Exception('Failed to load subscriptions');
      }
    } catch (e) {
      setState(() {
        isLoading = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('حدث خطأ: ${e.toString()}')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('تاريخ الاشتراكات')),
      body: Column(
        children: [
          // Filter chips
          Container(
            padding: EdgeInsets.symmetric(vertical: 8),
            height: 50,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: EdgeInsets.symmetric(horizontal: 16),
              children: [
                FilterChip(
                  label: Text('الكل'),
                  selected: selectedStatus == null,
                  onSelected: (selected) {
                    setState(() {
                      selectedStatus = null;
                    });
                    loadSubscriptions();
                  },
                ),
                SizedBox(width: 8),
                FilterChip(
                  label: Text('نشط'),
                  selected: selectedStatus == 'active',
                  onSelected: (selected) {
                    setState(() {
                      selectedStatus = 'active';
                    });
                    loadSubscriptions();
                  },
                ),
                SizedBox(width: 8),
                FilterChip(
                  label: Text('منتهي'),
                  selected: selectedStatus == 'expired',
                  onSelected: (selected) {
                    setState(() {
                      selectedStatus = 'expired';
                    });
                    loadSubscriptions();
                  },
                ),
                SizedBox(width: 8),
                FilterChip(
                  label: Text('ملغى'),
                  selected: selectedStatus == 'cancelled',
                  onSelected: (selected) {
                    setState(() {
                      selectedStatus = 'cancelled';
                    });
                    loadSubscriptions();
                  },
                ),
              ],
            ),
          ),

          // List
          Expanded(
            child: isLoading && subscriptions.isEmpty
                ? Center(child: CircularProgressIndicator())
                : subscriptions.isEmpty
                    ? Center(child: Text('لا توجد اشتراكات'))
                    : ListView.builder(
                        itemCount: subscriptions.length + (hasMore ? 1 : 0),
                        itemBuilder: (context, index) {
                          if (index == subscriptions.length) {
                            // Load more button
                            return Padding(
                              padding: EdgeInsets.all(16),
                              child: Center(
                                child: ElevatedButton(
                                  onPressed: () => loadSubscriptions(loadMore: true),
                                  child: Text('تحميل المزيد'),
                                ),
                              ),
                            );
                          }

                          final subscription = subscriptions[index];
                          return SubscriptionHistoryCard(
                            subscription: subscription,
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (context) => SubscriptionDetailsPage(
                                    subscriptionId: subscription.id,
                                  ),
                                ),
                              );
                            },
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

### مثال 4: صفحة تاريخ الدفعات

```dart
class PaymentHistoryPage extends StatefulWidget {
  @override
  _PaymentHistoryPageState createState() => _PaymentHistoryPageState();
}

class _PaymentHistoryPageState extends State<PaymentHistoryPage> {
  List<FinancialTransaction> transactions = [];
  bool isLoading = true;
  bool hasMore = true;
  int currentPage = 1;

  @override
  void initState() {
    super.initState();
    loadTransactions();
  }

  Future<void> loadTransactions({bool loadMore = false}) async {
    if (!loadMore) {
      setState(() {
        isLoading = true;
        currentPage = 1;
        transactions = [];
      });
    }

    try {
      final uri = Uri.parse('$baseUrl/api/v1/user/payments')
          .replace(queryParameters: {
        'page': currentPage.toString(),
        'per_page': '20',
        'sort_field': 'created_at',
        'sort_order': 'desc',
      });

      final response = await http.get(
        uri,
        headers: {
          'Authorization': 'Bearer $token',
          'Accept-Language': 'ar',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        final newTransactions = (data['data'] as List)
            .map((t) => FinancialTransaction.fromJson(t))
            .toList();

        final meta = data['meta'];
        final hasMoreData = meta['current_page'] < meta['last_page'];

        setState(() {
          if (loadMore) {
            transactions.addAll(newTransactions);
          } else {
            transactions = newTransactions;
          }
          hasMore = hasMoreData;
          currentPage = meta['current_page'] + 1;
          isLoading = false;
        });
      } else {
        throw Exception('Failed to load transactions');
      }
    } catch (e) {
      setState(() {
        isLoading = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('حدث خطأ: ${e.toString()}')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('تاريخ الدفعات')),
      body: isLoading && transactions.isEmpty
          ? Center(child: CircularProgressIndicator())
          : transactions.isEmpty
              ? Center(child: Text('لا توجد معاملات'))
              : ListView.builder(
                  itemCount: transactions.length + (hasMore ? 1 : 0),
                  itemBuilder: (context, index) {
                    if (index == transactions.length) {
                      return Padding(
                        padding: EdgeInsets.all(16),
                        child: Center(
                          child: ElevatedButton(
                            onPressed: () => loadTransactions(loadMore: true),
                            child: Text('تحميل المزيد'),
                          ),
                        ),
                      );
                    }

                    final transaction = transactions[index];
                    return TransactionCard(transaction: transaction);
                  },
                ),
    );
  }
}

class TransactionCard extends StatelessWidget {
  final FinancialTransaction transaction;

  const TransactionCard({Key? key, required this.transaction}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: ListTile(
        leading: _getIcon(),
        title: Text(_getTypeText()),
        subtitle: Text(
          transaction.description ?? '',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        trailing: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              '${transaction.amount > 0 ? '+' : ''}\$${transaction.amount.toStringAsFixed(2)}',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: transaction.amount > 0 ? Colors.green : Colors.red,
              ),
            ),
            SizedBox(height: 4),
            Text(
              _formatDate(transaction.createdAt),
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
          ],
        ),
      ),
    );
  }

  Widget _getIcon() {
    switch (transaction.type) {
      case 'subscription_payment':
        return Icon(Icons.payment, color: Colors.blue);
      case 'upgrade_payment':
        return Icon(Icons.trending_up, color: Colors.orange);
      case 'refund':
        return Icon(Icons.refund, color: Colors.red);
      default:
        return Icon(Icons.receipt, color: Colors.grey);
    }
  }

  String _getTypeText() {
    switch (transaction.type) {
      case 'subscription_payment':
        return 'دفع اشتراك';
      case 'upgrade_payment':
        return 'ترقية اشتراك';
      case 'refund':
        return 'استرداد';
      default:
        return transaction.type;
    }
  }

  String _formatDate(String dateString) {
    final date = DateTime.parse(dateString);
    return '${date.day}/${date.month}/${date.year}';
  }
}
```

---

## UI/UX Guidelines

### الألوان

- **الأخضر**: للخطة الحالية، الحالة النشطة، المعاملات المكتملة
- **الأزرق**: للأزرار الأساسية، دفع اشتراك
- **البرتقالي**: للترقية، المعاملات المعلقة
- **الرمادي**: للحالات المعطلة، المعاملات الفاشلة
- **الأحمر**: للتحذيرات، الإلغاء، الاستردادات

### الأيقونات

- ✓ للخطة الحالية، المعاملات المكتملة
- ⭐ للخطة المميزة
- 💳 للدفع
- ⬆️ للترقية
- 📅 للتواريخ
- ⚙️ للإدارة
- ⚠️ للتحذيرات

### الرسائل

#### رسائل النجاح
- "تم إنشاء الاشتراك بنجاح"
- "تم ترقية الاشتراك بنجاح"
- "تم إلغاء التجديد التلقائي"

#### رسائل الخطأ
- "حدث خطأ. يرجى المحاولة مرة أخرى"
- "فشل الدفع. يرجى التحقق من معلومات البطاقة"
- "الاشتراك غير موجود"

#### رسائل التأكيد
- "هل أنت متأكد من إلغاء التجديد التلقائي؟"
- "الاشتراك سيستمر حتى [تاريخ]"

---

## معالجة الأخطاء

### Error Codes

| Code | المعنى | الحل |
|------|--------|------|
| 401 | Unauthorized | إعادة توجيه لصفحة الدخول |
| 403 | Forbidden | عرض رسالة "ليس لديك صلاحية" |
| 404 | Not Found | عرض رسالة "غير موجود" |
| 400 | Bad Request | عرض رسالة الخطأ من API |
| 500 | Server Error | عرض رسالة عامة مع إمكانية إعادة المحاولة |

### Error Handling Example

```dart
try {
  final response = await http.get(uri, headers: headers);
  
  if (response.statusCode == 200) {
    // Success
  } else if (response.statusCode == 401) {
    // Redirect to login
    Navigator.pushReplacementNamed(context, '/login');
  } else if (response.statusCode == 404) {
    // Show not found message
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('غير موجود')),
    );
  } else {
    // Show error message
    final error = json.decode(response.body);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(error['message'] ?? 'حدث خطأ')),
    );
  }
} catch (e) {
  // Network error
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(content: Text('خطأ في الاتصال. يرجى المحاولة مرة أخرى')),
  );
}
```

---

## Navigation Flow

```
Main Menu
  └─> Plans Page
       ├─> Subscribe Flow (إذا غير مشترك)
       │    ├─> Prepare Payment
       │    ├─> Stripe Payment Sheet
       │    └─> Success Page
       │
       ├─> Upgrade Flow (إذا مشترك)
       │    ├─> Confirm Upgrade Dialog
       │    ├─> Prepare Payment
       │    ├─> Stripe Payment Sheet
       │    └─> Success Page
       │
       └─> Manage Subscription (إذا مشترك)
            ├─> Subscription Details Page
            │    ├─> Cancel/Resume Auto-Renew
            │    ├─> Upgrade Subscription
            │    ├─> Subscription History
            │    └─> Payment History
            │
            ├─> Subscription History Page
            │    └─> Subscription Details Page
            │
            └─> Payment History Page
```

---

## ملاحظات مهمة

1. **الأمان**: جميع endpoints تتطلب authentication
2. **Pagination**: جميع قوائم البيانات تدعم pagination
3. **Filtering**: الدفعات تدعم تصفية حسب النوع والحالة والتاريخ
4. **Performance**: استخدام lazy loading للقوائم الطويلة
5. **Offline Support**: حفظ البيانات محلياً للعرض عند عدم وجود اتصال
6. **Error Handling**: معالجة شاملة للأخطاء مع رسائل واضحة
7. **Loading States**: عرض loading indicators أثناء جلب البيانات
8. **Empty States**: عرض رسائل مناسبة عند عدم وجود بيانات

---

## Resources

- [Stripe Flutter SDK Documentation](https://stripe.dev/stripe-flutter/)
- [Stripe Payment Sheet Guide](https://stripe.com/docs/payments/accept-a-payment?platform=flutter)
- [Diplomasi Backend API Documentation](./SUBSCRIPTIONS_FLUTTER_GUIDE.md)

---

## Support

للدعم الفني، يرجى التواصل مع فريق التطوير.
