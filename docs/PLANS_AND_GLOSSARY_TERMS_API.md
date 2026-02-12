# وثائق API - الخطط والمصطلحات (Plans & Glossary Terms)

## نظرة عامة

هذا المستند يشرح جميع الـ endpoints المتعلقة بـ:
- **الخطط (Plans)**: إدارة خطط الاشتراك في النظام
- **المصطلحات (Glossary Terms)**: إدارة قاموس المصطلحات التعليمية

---

## 🔐 المصادقة والصلاحيات

جميع الـ endpoints التالية تتطلب:
- **Admin Endpoints**: مصادقة `auth:sanctum` + صلاحيات Admin (`AdminMiddleware`)
- **User Endpoints**: بعض الـ endpoints متاحة للعامة، وبعضها يتطلب مصادقة `auth:sanctum`

---

## 📋 الخطط (Plans)

### Base URL
- **Admin**: `/api/v1/admin/plans`
- **User (Public)**: `/api/v1/user/plans`

---

### 1. عرض قائمة الخطط (Index)

#### Admin Endpoint
```
GET /api/v1/admin/plans
```

#### User Endpoint (Public)
```
GET /api/v1/user/plans
```

**الوصف**: استرجاع قائمة جميع الخطط المتاحة

**المصادقة**: 
- Admin: يتطلب مصادقة + صلاحيات Admin
- User: متاح للعامة

**Query Parameters** (اختياري):
- `per_page`: عدد النتائج في الصفحة (افتراضي: 20)
- `sort_field`: حقل الترتيب (افتراضي: `created_at`)
- `sort_order`: اتجاه الترتيب `asc` أو `desc` (افتراضي: `desc`)
- `search`: البحث في الحقول (`name`, `description`)
- `price`: فلترة حسب السعر
- `interval`: فلترة حسب نوع الفترة (`monthly`, `semi_annual`, `annual`)
- `created_at`: فلترة حسب تاريخ الإنشاء

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "الخطة الأساسية",
      "price": 99.99,
      "interval": "monthly",
      "description": "وصف الخطة",
      "icon_url": "https://example.com/icon.png",
      "features": ["ميزة 1", "ميزة 2"],
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
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

### 2. عرض خطة محددة (Show)

#### Admin Endpoint
```
GET /api/v1/admin/plans/{id}
```

#### User Endpoint (Public)
```
GET /api/v1/user/plans/{id}
```

**الوصف**: استرجاع تفاصيل خطة محددة

**المصادقة**: 
- Admin: يتطلب مصادقة + صلاحيات Admin
- User: متاح للعامة

**Path Parameters**:
- `id` (required): معرف الخطة

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "الخطة الأساسية",
    "price": 99.99,
    "interval": "monthly",
    "description": "وصف الخطة",
    "icon_url": "https://example.com/icon.png",
    "features": ["ميزة 1", "ميزة 2"],
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z",
    "subscriptions": [],
    "subscription_events": []
  }
}
```

**Errors**:
- `404`: الخطة غير موجودة

---

### 3. إنشاء خطة جديدة (Create)

```
POST /api/v1/admin/plans
```

**الوصف**: إنشاء خطة جديدة

**المصادقة**: يتطلب مصادقة + صلاحيات Admin + صلاحية `canCreate`

**Request Body**:
```json
{
  "name": "الخطة الأساسية",
  "price": 99.99,
  "interval": "monthly",
  "description": "وصف الخطة (اختياري)",
  "features": ["ميزة 1", "ميزة 2"],
  "icon_url": "path/to/icon.png"
}
```

**Validation Rules**:
- `name` (required): اسم الخطة، نص، أقصى 100 حرف
- `price` (required): السعر، رقم، يجب أن يكون أكبر من أو يساوي 0
- `interval` (required): نوع الفترة، يجب أن يكون أحد: `monthly`, `semi_annual`, `annual`
- `description` (optional): وصف الخطة، نص
- `features` (optional): مصفوفة من الميزات، كل عنصر نص
- `icon_url` (optional): رابط الأيقونة، نص، أقصى 100 حرف

**Response** (201 Created):
```json
{
  "success": true,
  "message": "messages.plan.created",
  "data": {
    "id": 1,
    "name": "الخطة الأساسية",
    "price": 99.99,
    "interval": "monthly",
    "description": "وصف الخطة",
    "icon_url": "https://example.com/icon.png",
    "features": ["ميزة 1", "ميزة 2"],
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

**Errors**:
- `422`: أخطاء التحقق من البيانات
- `403`: لا توجد صلاحية للإنشاء

---

### 4. تحديث خطة (Update)

```
PUT /api/v1/admin/plans/{id}
```

**الوصف**: تحديث بيانات خطة موجودة

**المصادقة**: يتطلب مصادقة + صلاحيات Admin + صلاحية `canUpdate`

**Path Parameters**:
- `id` (required): معرف الخطة

**Request Body** (جميع الحقول اختيارية - `sometimes`):
```json
{
  "name": "الخطة المحدثة",
  "price": 149.99,
  "interval": "annual",
  "description": "وصف محدث",
  "features": ["ميزة جديدة"],
  "icon_url": "path/to/new-icon.png"
}
```

**Validation Rules**:
- جميع الحقول نفس قواعد الإنشاء، لكنها اختيارية (`sometimes`)

**Response** (200 OK):
```json
{
  "success": true,
  "message": "messages.plan.updated",
  "data": {
    "id": 1,
    "name": "الخطة المحدثة",
    "price": 149.99,
    "interval": "annual",
    "description": "وصف محدث",
    "icon_url": "https://example.com/new-icon.png",
    "features": ["ميزة جديدة"],
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-02T00:00:00.000000Z"
  }
}
```

**Errors**:
- `404`: الخطة غير موجودة
- `422`: أخطاء التحقق من البيانات
- `403`: لا توجد صلاحية للتحديث

---

### 5. حذف خطة (Delete)

```
DELETE /api/v1/admin/plans/{id}
```

**الوصف**: حذف خطة

**المصادقة**: يتطلب مصادقة + صلاحيات Admin + صلاحية `canDelete`

**Path Parameters**:
- `id` (required): معرف الخطة

**Response** (200 OK):
```json
{
  "success": true,
  "message": "messages.plan.deleted"
}
```

**Errors**:
- `404`: الخطة غير موجودة
- `403`: لا توجد صلاحية للحذف

---

### 6. إعادة ترتيب الخطة (Reorder)

```
PUT /api/v1/admin/plans/{id}/reorder
```

**الوصف**: تغيير ترتيب الخطة في القائمة

**المصادقة**: يتطلب مصادقة + صلاحيات Admin + صلاحية `canReorder`

**Path Parameters**:
- `id` (required): معرف الخطة

**Request Body**:
```json
{
  "order_index": 2
}
```

**Validation Rules**:
- `order_index` (required): الفهرس الجديد للترتيب

**Response** (200 OK):
```json
{
  "success": true,
  "message": "messages.plan.reordered",
  "data": [...],
  "meta": {...}
}
```

**Errors**:
- `404`: الخطة غير موجودة
- `422`: أخطاء التحقق من البيانات
- `403`: لا توجد صلاحية لإعادة الترتيب

---

## 📚 المصطلحات (Glossary Terms)

### Base URL
- **Admin**: `/api/v1/admin/glossary-terms`
- **User (Public)**: `/api/v1/user/glossary-terms`

---

### 1. عرض قائمة المصطلحات (Index)

#### Admin Endpoint
```
GET /api/v1/admin/glossary-terms
```

#### User Endpoint (Public)
```
GET /api/v1/user/glossary-terms
```

**الوصف**: استرجاع قائمة جميع المصطلحات

**المصادقة**: 
- Admin: يتطلب مصادقة + صلاحيات Admin
- User: متاح للعامة

**Query Parameters** (اختياري):
- `per_page`: عدد النتائج في الصفحة (افتراضي: 20)
- `sort_field`: حقل الترتيب (افتراضي: `created_at`)
- `sort_order`: اتجاه الترتيب `asc` أو `desc` (افتراضي: `desc`)
- `search`: البحث في الحقول (`term`, `definition`)
- `language`: فلترة حسب اللغة
- `created_at`: فلترة حسب تاريخ الإنشاء

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "term": "الدبلوماسية",
      "definition": "فن وعلم إدارة العلاقات الدولية",
      "language": "ar",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 50,
    "last_page": 3
  }
}
```

---

### 2. عرض مصطلح محدد (Show)

#### Admin Endpoint
```
GET /api/v1/admin/glossary-terms/{id}
```

#### User Endpoint (Public)
```
GET /api/v1/user/glossary-terms/{id}
```

**الوصف**: استرجاع تفاصيل مصطلح محدد

**المصادقة**: 
- Admin: يتطلب مصادقة + صلاحيات Admin
- User: متاح للعامة

**Path Parameters**:
- `id` (required): معرف المصطلح

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "term": "الدبلوماسية",
    "definition": "فن وعلم إدارة العلاقات الدولية",
    "language": "ar",
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

**Errors**:
- `404`: المصطلح غير موجود

---

### 3. إنشاء مصطلح جديد (Create)

```
POST /api/v1/admin/glossary-terms
```

**الوصف**: إنشاء مصطلح جديد في القاموس

**المصادقة**: يتطلب مصادقة + صلاحيات Admin + صلاحية `canCreate`

**Request Body**:
```json
{
  "term": "الدبلوماسية",
  "definition": "فن وعلم إدارة العلاقات الدولية",
  "language": "ar"
}
```

**Validation Rules**:
- `term` (required): المصطلح، نص، أقصى 255 حرف
- `definition` (required): التعريف، نص
- `language` (optional): اللغة، نص، أقصى 10 أحرف

**Response** (201 Created):
```json
{
  "success": true,
  "message": "messages.glossary_term.created",
  "data": {
    "id": 1,
    "term": "الدبلوماسية",
    "definition": "فن وعلم إدارة العلاقات الدولية",
    "language": "ar",
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

**Errors**:
- `422`: أخطاء التحقق من البيانات
- `403`: لا توجد صلاحية للإنشاء

---

### 4. تحديث مصطلح (Update)

```
PUT /api/v1/admin/glossary-terms/{id}
```

**الوصف**: تحديث بيانات مصطلح موجود

**المصادقة**: يتطلب مصادقة + صلاحيات Admin + صلاحية `canUpdate`

**Path Parameters**:
- `id` (required): معرف المصطلح

**Request Body** (جميع الحقول اختيارية - `sometimes`):
```json
{
  "term": "الدبلوماسية المحدثة",
  "definition": "تعريف محدث",
  "language": "en"
}
```

**Validation Rules**:
- جميع الحقول نفس قواعد الإنشاء، لكنها اختيارية (`sometimes`)

**Response** (200 OK):
```json
{
  "success": true,
  "message": "messages.glossary_term.updated",
  "data": {
    "id": 1,
    "term": "الدبلوماسية المحدثة",
    "definition": "تعريف محدث",
    "language": "en",
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-02T00:00:00.000000Z"
  }
}
```

**Errors**:
- `404`: المصطلح غير موجود
- `422`: أخطاء التحقق من البيانات
- `403`: لا توجد صلاحية للتحديث

---

### 5. حذف مصطلح (Delete)

```
DELETE /api/v1/admin/glossary-terms/{id}
```

**الوصف**: حذف مصطلح من القاموس

**المصادقة**: يتطلب مصادقة + صلاحيات Admin + صلاحية `canDelete`

**Path Parameters**:
- `id` (required): معرف المصطلح

**Response** (200 OK):
```json
{
  "success": true,
  "message": "messages.glossary_term.deleted"
}
```

**Errors**:
- `404`: المصطلح غير موجود
- `403`: لا توجد صلاحية للحذف

---

### 6. إعادة ترتيب المصطلح (Reorder)

```
PUT /api/v1/admin/glossary-terms/{id}/reorder
```

**الوصف**: تغيير ترتيب المصطلح في القائمة

**المصادقة**: يتطلب مصادقة + صلاحيات Admin + صلاحية `canReorder`

**Path Parameters**:
- `id` (required): معرف المصطلح

**Request Body**:
```json
{
  "order_index": 5
}
```

**Validation Rules**:
- `order_index` (required): الفهرس الجديد للترتيب

**Response** (200 OK):
```json
{
  "success": true,
  "message": "messages.glossary_term.reordered",
  "data": [...],
  "meta": {...}
}
```

**Errors**:
- `404`: المصطلح غير موجود
- `422`: أخطاء التحقق من البيانات
- `403`: لا توجد صلاحية لإعادة الترتيب

---

## 📝 ملاحظات عامة

### Response Format
جميع الـ responses تتبع نفس التنسيق:
```json
{
  "success": true/false,
  "message": "رسالة (اختياري)",
  "data": {...},
  "meta": {...} // موجود فقط في قوائم paginated
}
```

### Error Responses
```json
{
  "success": false,
  "message": "رسالة الخطأ",
  "errors": {
    "field": ["رسالة خطأ التحقق"]
  }
}
```

### Status Codes
- `200`: نجاح العملية
- `201`: تم الإنشاء بنجاح
- `400`: طلب خاطئ
- `401`: غير مصرح (غير مصادق)
- `403`: ممنوع (لا توجد صلاحية)
- `404`: غير موجود
- `422`: أخطاء التحقق من البيانات
- `500`: خطأ في الخادم

### Headers المطلوبة
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
Accept-Language: ar (اختياري)
```

---

## 🔗 روابط إضافية

- **Base API URL**: `/api/v1`
- **Admin Routes**: `/api/v1/admin/*`
- **User Routes**: `/api/v1/user/*`

---

**آخر تحديث**: 2024
