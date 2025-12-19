# دليل اختبار API Endpoints

## الملفات المتوفرة

1. **`API_ENDPOINTS_TESTING.md`** - دليل شامل بكل الـ endpoints مع أمثلة
2. **`Diplomasi_API.postman_collection.json`** - Postman Collection جاهز للاستيراد
3. **`test_endpoints.php`** - سكريبت PHP للاختبار السريع

---

## طريقة الاستخدام

### 1. استخدام Postman Collection

1. افتح Postman
2. اضغط على **Import**
3. اختر ملف `Diplomasi_API.postman_collection.json`
4. بعد الاستيراد:
   - اضبط المتغيرات:
     - `base_url`: `http://localhost:8000`
     - `token`: (سيتم ملؤه بعد Login)
5. ابدأ بـ **Login** request واحفظ الـ token في المتغير `token`
6. اختبر باقي الـ endpoints

### 2. استخدام السكريبت PHP

```bash
php test_endpoints.php
```

السكريبت سيقوم بـ:
- تسجيل الدخول تلقائياً
- اختبار جميع الـ endpoints الرئيسية
- عرض النتائج (✓ PASS / ✗ FAIL)

### 3. استخدام curl (أمثلة)

#### Login
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "admin@demo.test",
    "password": "Password123!"
  }'
```

#### Get Roles (Dashboard)
```bash
curl -X GET http://localhost:8000/api/v1/admin/roles \
  -H "X-Context: dashboard" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

#### Get Courses (Public - App Context)
```bash
curl -X GET http://localhost:8000/api/v1/user/courses \
  -H "X-Context: app" \
  -H "Accept: application/json"
```

---

## بيانات الاختبار (من Seeder)

### Users
- **Super Admin**: `superadmin@demo.test` / `Password123!`
- **Dashboard Admin**: `admin@demo.test` / `Password123!`
- **Regular Users**: `user01@demo.test` to `user50@demo.test` / `Password123!`

### Sample Data
- **12 Courses** (بعضها published، بعضها لا)
- **36 Levels** (3 لكل course)
- **216 Lessons** (6 لكل level)
- **648 Lesson Questions** (3 لكل lesson)
- **20 Subscriptions**
- **12 Articles**
- **8 FAQs**
- **4 Pages**

---

## ملاحظات مهمة

### 1. Context Header
- **Dashboard**: `X-Context: dashboard` - للوصول لـ admin endpoints
- **App**: `X-Context: app` - للوصول لـ public/user endpoints

### 2. Authentication
- معظم الـ admin endpoints تحتاج `Authorization: Bearer {token}`
- الـ public endpoints (مثل `GET /user/courses`) لا تحتاج token

### 3. Pagination
معظم الـ list endpoints تدعم:
- `per_page`: عدد العناصر (default: 20)
- `page`: رقم الصفحة
- `search`: بحث نصي
- `sort_field`: حقل الترتيب
- `sort_order`: `asc` أو `desc`

### 4. Filtering
مثال:
```
GET /api/v1/admin/courses?is_published=true&is_free=false&per_page=10
```

---

## اختبار سريع (Quick Test)

### 1. تأكد أن الـ server شغال
```bash
php artisan serve
```

### 2. اختبر Login
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@demo.test","password":"Password123!"}'
```

### 3. احفظ الـ token واستخدمه
```bash
# احفظ الـ token في متغير
TOKEN="YOUR_TOKEN_HERE"

# اختبر endpoint
curl -X GET http://localhost:8000/api/v1/admin/roles \
  -H "X-Context: dashboard" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

---

## Troubleshooting

### Error 403: Permission Denied
- تأكد أن الـ user عنده الصلاحيات المطلوبة
- تأكد من الـ `X-Context` header (dashboard vs app)

### Error 401: Unauthorized
- تأكد أن الـ token صحيح
- جرب تسجيل الدخول مرة ثانية

### Error 404: Not Found
- تأكد من الـ route path
- تأكد أن الـ ID موجود في قاعدة البيانات

### Error 422: Validation Error
- راجع الـ request body
- تأكد من جميع الحقول المطلوبة

---

## Next Steps

1. استورد Postman Collection
2. اختبر Login واحفظ الـ token
3. ابدأ باختبار الـ endpoints حسب الحاجة
4. استخدم `API_ENDPOINTS_TESTING.md` كمرجع شامل


