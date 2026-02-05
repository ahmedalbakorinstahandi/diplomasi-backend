# API Endpoints Testing Guide

## Base URL
```
http://localhost:8000/api/v1
```

## Headers المطلوبة

### للداشبورد (Dashboard Context)
```
X-Context: dashboard
Authorization: Bearer {token}
Accept: application/json
```

### للتطبيق (App Context)
```
X-Context: app
Authorization: Bearer {token} (اختياري للـ public endpoints)
Accept: application/json
```

---

## 1. Authentication Endpoints

### 1.1 Login
```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@demo.test",
  "password": "Password123!"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {...},
    "token": "1|..."
  }
}
```

### 1.2 Register
```http
POST /api/v1/auth/register
Content-Type: application/json

{
  "first_name": "Test",
  "last_name": "User",
  "email": "test@example.com",
  "phone": "+201234567890",
  "password": "Password123!",
  "password_confirmation": "Password123!"
}
```

### 1.3 Verify OTP
```http
POST /api/v1/auth/verify-otp
Content-Type: application/json

{
  "email": "test@example.com",
  "otp": "123456"
}
```

### 1.4 Forgot Password
```http
POST /api/v1/auth/forgot-password
Content-Type: application/json

{
  "email": "test@example.com"
}
```

### 1.5 Reset Password
```http
POST /api/v1/auth/reset-password
Authorization: Bearer {token}
Content-Type: application/json

{
  "current_password": "OldPassword123!",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!"
}
```

### 1.6 Logout
```http
POST /api/v1/auth/logout
Authorization: Bearer {token}
```

---

## 2. Admin Endpoints (Dashboard Context)

### 2.1 Users Management

#### List Users
```http
GET /api/v1/admin/users?per_page=20&page=1&search=test
X-Context: dashboard
Authorization: Bearer {token}
```

#### Get User
```http
GET /api/v1/admin/users/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Create User
```http
POST /api/v1/admin/users
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "first_name": "New",
  "last_name": "User",
  "email": "newuser@example.com",
  "phone": "+201234567891",
  "password": "Password123!",
  "status": "active"
}
```

#### Update User
```http
PUT /api/v1/admin/users/1
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "first_name": "Updated",
  "last_name": "Name",
  "status": "active"
}
```

#### Delete User
```http
DELETE /api/v1/admin/users/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Get Profile (Admin)
```http
GET /api/v1/admin/me
X-Context: dashboard
Authorization: Bearer {token}
```

#### Update Profile (Admin)
```http
PUT /api/v1/admin/me
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "first_name": "Updated",
  "last_name": "Name"
}
```

---

### 2.2 Roles & Permissions Management

#### List Permissions
```http
GET /api/v1/admin/permissions?per_page=50
X-Context: dashboard
Authorization: Bearer {token}
```

#### List Roles
```http
GET /api/v1/admin/roles?per_page=20
X-Context: dashboard
Authorization: Bearer {token}
```

#### Get Role
```http
GET /api/v1/admin/roles/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Create Role
```http
POST /api/v1/admin/roles
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "editor",
  "description": "Content Editor Role",
  "is_default": false
}
```

#### Update Role
```http
PUT /api/v1/admin/roles/1
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "editor",
  "description": "Updated description",
  "is_default": false
}
```

#### Delete Role
```http
DELETE /api/v1/admin/roles/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Sync Role Permissions
```http
PUT /api/v1/admin/roles/1/permissions
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "permission_names": [
    "article.view",
    "article.create",
    "article.update",
    "article.delete"
  ]
}
```

---

### 2.3 Courses Management

#### List Courses
```http
GET /api/v1/admin/courses?per_page=20&is_published=true
X-Context: dashboard
Authorization: Bearer {token}
```

#### Get Course
```http
GET /api/v1/admin/courses/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Create Course
```http
POST /api/v1/admin/courses
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "New Course",
  "description": "Course description",
  "image_url": "https://picsum.photos/id/1011/800/400",
  "is_published": true,
  "is_free": false
}
```

#### Update Course
```http
PUT /api/v1/admin/courses/1
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Updated Course Title",
  "is_published": true
}
```

#### Delete Course
```http
DELETE /api/v1/admin/courses/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Reorder Course
```http
PUT /api/v1/admin/courses/1/reorder
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "order_index": 5
}
```

---

### 2.4 Lessons Management

#### List Lessons
```http
GET /api/v1/admin/lessons?per_page=20&level_id=1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Get Lesson
```http
GET /api/v1/admin/lessons/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Create Lesson
```http
POST /api/v1/admin/lessons
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "level_id": 1,
  "lesson_number": "1",
  "title": "New Lesson",
  "description": "Lesson description",
  "video_url": "https://www.youtube.com/watch?v=5MgBikgcWnY",
  "content": "Lesson content here",
  "order_index": 1,
  "is_published": true
}
```

#### Update Lesson
```http
PUT /api/v1/admin/lessons/1
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Updated Lesson Title",
  "is_published": true
}
```

#### Delete Lesson
```http
DELETE /api/v1/admin/lessons/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Reorder Lesson
```http
PUT /api/v1/admin/lessons/1/reorder
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "order_index": 3
}
```

---

### 2.5 Levels Management

#### List Levels
```http
GET /api/v1/admin/levels?per_page=20&course_id=1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Get Level
```http
GET /api/v1/admin/levels/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Create Level
```http
POST /api/v1/admin/levels
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "course_id": 1,
  "level_number": 1,
  "title": "Level 1",
  "description": "Level description",
  "is_published": true,
  "is_free": true,
  "has_certificate": false,
  "order_index": 1
}
```

#### Update Level
```http
PUT /api/v1/admin/levels/1
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Updated Level Title",
  "is_published": true
}
```

#### Delete Level
```http
DELETE /api/v1/admin/levels/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Reorder Level
```http
PUT /api/v1/admin/levels/1/reorder
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "order_index": 2
}
```

---

### 2.6 Scenarios Management

#### List Scenarios
```http
GET /api/v1/admin/scenarios?per_page=20&level_id=1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Get Scenario
```http
GET /api/v1/admin/scenarios/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Create Scenario
```http
POST /api/v1/admin/scenarios
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "level_id": 1,
  "title": "New Scenario",
  "description": {"ar": "وصف السيناريو"},
  "is_published": true,
  "is_free": false,
  "order_index": 1
}
```

#### Update Scenario
```http
PUT /api/v1/admin/scenarios/1
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Updated Scenario",
  "is_published": true
}
```

#### Delete Scenario
```http
DELETE /api/v1/admin/scenarios/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Reorder Scenario
```http
PUT /api/v1/admin/scenarios/1/reorder
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "order_index": 2
}
```

---

### 2.7 Articles Management

#### List Articles
```http
GET /api/v1/admin/articles?per_page=20&is_published=true
X-Context: dashboard
Authorization: Bearer {token}
```

#### Get Article
```http
GET /api/v1/admin/articles/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Create Article
```http
POST /api/v1/admin/articles
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "New Article",
  "slug": "new-article",
  "content": "Article content here",
  "is_published": true,
  "published_at": "2025-12-18 10:00:00"
}
```

#### Update Article
```http
PUT /api/v1/admin/articles/1
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Updated Article",
  "is_published": true
}
```

#### Delete Article
```http
DELETE /api/v1/admin/articles/1
X-Context: dashboard
Authorization: Bearer {token}
```

---

### 2.8 Subscriptions Management

#### List Subscriptions
```http
GET /api/v1/admin/subscriptions?per_page=20&status=active
X-Context: dashboard
Authorization: Bearer {token}
```

#### Get Subscription
```http
GET /api/v1/admin/subscriptions/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Create Subscription
```http
POST /api/v1/admin/subscriptions
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "user_id": 1,
  "plan_id": 1,
  "start_date": "2025-12-18",
  "end_date": "2026-01-18",
  "status": "active",
  "price": 19.99,
  "currency": "USD",
  "auto_renew": true
}
```

#### Update Subscription
```http
PUT /api/v1/admin/subscriptions/1
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "active",
  "auto_renew": false
}
```

#### Delete Subscription
```http
DELETE /api/v1/admin/subscriptions/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Cancel Subscription
```http
POST /api/v1/admin/subscriptions/1/cancel
X-Context: dashboard
Authorization: Bearer {token}
```

#### Renew Subscription
```http
POST /api/v1/admin/subscriptions/1/renew
X-Context: dashboard
Authorization: Bearer {token}
```

---

### 2.9 Notifications Management

#### List Notifications
```http
GET /api/v1/admin/notifications?per_page=20
X-Context: dashboard
Authorization: Bearer {token}
```

#### Get Notification
```http
GET /api/v1/admin/notifications/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Create Notification
```http
POST /api/v1/admin/notifications
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "user_id": null,
  "title": "System Notification",
  "body": "Notification message",
  "type": "system",
  "data": {"key": "value"}
}
```

#### Update Notification
```http
PUT /api/v1/admin/notifications/1
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Updated Notification",
  "body": "Updated message"
}
```

#### Delete Notification
```http
DELETE /api/v1/admin/notifications/1
X-Context: dashboard
Authorization: Bearer {token}
```

---

### 2.10 Settings Management

#### List Settings
```http
GET /api/v1/admin/settings?per_page=20
X-Context: dashboard
Authorization: Bearer {token}
```

#### Get Setting
```http
GET /api/v1/admin/settings/1
X-Context: dashboard
Authorization: Bearer {token}
```

#### Get Setting by Key
```http
GET /api/v1/admin/settings/app.name
X-Context: dashboard
Authorization: Bearer {token}
```

#### Create Setting
```http
POST /api/v1/admin/settings
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "key_name": "app.version",
  "value": "1.0.0",
  "type": "text",
  "is_settings": true
}
```

#### Update Setting
```http
PUT /api/v1/admin/settings/app.name
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "value": "New App Name"
}
```

#### Update Many Settings
```http
PUT /api/v1/admin/settings
X-Context: dashboard
Authorization: Bearer {token}
Content-Type: application/json

{
  "settings": [
    {"key_name": "app.name", "value": "Diplomasi"},
    {"key_name": "app.version", "value": "1.0.1"}
  ]
}
```

#### Delete Setting
```http
DELETE /api/v1/admin/settings/app.name
X-Context: dashboard
Authorization: Bearer {token}
```

---

## 3. User Endpoints (App Context)

### 3.1 Public Content (لا يحتاج authentication)

#### List Courses (Public)
```http
GET /api/v1/user/courses?per_page=20
X-Context: app
```

#### Get Course (Public)
```http
GET /api/v1/user/courses/1
X-Context: app
```

#### List Lessons (Public)
```http
GET /api/v1/user/lessons?per_page=20&level_id=1
X-Context: app
```

#### Get Lesson (Public)
```http
GET /api/v1/user/lessons/1
X-Context: app
```

#### List Levels (Public)
```http
GET /api/v1/user/levels?per_page=20&course_id=1
X-Context: app
```

#### Get Level (Public)
```http
GET /api/v1/user/levels/1
X-Context: app
```

#### List Scenarios (Public)
```http
GET /api/v1/user/scenarios?per_page=20&level_id=1
X-Context: app
```

#### Get Scenario (Public)
```http
GET /api/v1/user/scenarios/1
X-Context: app
```

#### List Articles (Public)
```http
GET /api/v1/user/articles?per_page=20
X-Context: app
```

#### Get Article (Public)
```http
GET /api/v1/user/articles/1
X-Context: app
```

#### Get Public Settings
```http
GET /api/v1/user/settings/public
X-Context: app
```

---

### 3.2 Authenticated User Endpoints

#### Get Dashboard Permissions
```http
GET /api/v1/user/permissions
X-Context: dashboard
Authorization: Bearer {token}
```

#### Get User Profile
```http
GET /api/v1/user/me
X-Context: app
Authorization: Bearer {token}
```

#### Update User Profile
```http
PUT /api/v1/user/me
X-Context: app
Authorization: Bearer {token}
Content-Type: application/json

{
  "first_name": "Updated",
  "last_name": "Name",
  "language": "ar"
}
```

---

### 3.3 Progress Management

#### List Progress
```http
GET /api/v1/user/progress/courses?per_page=20
X-Context: app
Authorization: Bearer {token}
```

#### Get Progress Item
```http
GET /api/v1/user/progress/courses/1
X-Context: app
Authorization: Bearer {token}
```

#### Create Progress
```http
POST /api/v1/user/progress/courses
X-Context: app
Authorization: Bearer {token}
Content-Type: application/json

{
  "course_id": 1,
  "status": "active",
  "started_at": "2025-12-18 10:00:00"
}
```

#### Update Progress
```http
PUT /api/v1/user/progress/courses/1
X-Context: app
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "completed",
  "completed_at": "2025-12-18 12:00:00"
}
```

---

### 3.4 Scenarios - User Actions

#### Start Scenario Attempt
```http
POST /api/v1/user/scenarios/start-attempt
X-Context: app
Authorization: Bearer {token}
Content-Type: application/json

{
  "scenario_id": 1
}
```

#### Submit Scenario Answer
```http
POST /api/v1/user/scenarios/submit-answer
X-Context: app
Authorization: Bearer {token}
Content-Type: application/json

{
  "attempt_id": 1,
  "question_id": 1,
  "option_id": 1
}
```

---

### 3.5 Notifications - User Actions

#### List User Notifications
```http
GET /api/v1/user/notifications?per_page=20
X-Context: app
Authorization: Bearer {token}
```

#### Get Notification
```http
GET /api/v1/user/notifications/1
X-Context: app
Authorization: Bearer {token}
```

#### Mark Notification as Read
```http
POST /api/v1/user/notifications/1/read
X-Context: app
Authorization: Bearer {token}
```

#### Mark All Notifications as Read
```http
POST /api/v1/user/notifications/mark-all-read
X-Context: app
Authorization: Bearer {token}
```

#### Get Unread Count
```http
GET /api/v1/user/notifications/unread-count
X-Context: app
Authorization: Bearer {token}
```

---

## 4. General Endpoints (Public)

#### Get Setting by Key
```http
GET /api/v1/general/settings/app.name
```

---

## Testing Tips

### 1. الحصول على Token
```bash
# Login للحصول على token
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "admin@demo.test",
    "password": "Password123!"
  }'
```

### 2. استخدام Token في Requests
```bash
# Example: Get roles
curl -X GET http://localhost:8000/api/v1/admin/roles \
  -H "X-Context: dashboard" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

### 3. Test Users من Seeder
- **Super Admin**: `superadmin@demo.test` / `Password123!`
- **Dashboard Admin**: `admin@demo.test` / `Password123!`
- **Regular Users**: `user01@demo.test` to `user50@demo.test` / `Password123!`

### 4. Context Testing
- **Dashboard**: استخدم `X-Context: dashboard` للوصول لـ admin endpoints
- **App**: استخدم `X-Context: app` للوصول لـ public/user endpoints

### 5. Filtering & Pagination
معظم الـ list endpoints تدعم:
- `per_page`: عدد العناصر في الصفحة (default: 20)
- `page`: رقم الصفحة
- `search`: بحث نصي
- `sort_field`: حقل الترتيب
- `sort_order`: `asc` أو `desc`

---

## Postman Collection

يمكنك استيراد ملف `Diplomasi_API.postman_collection.json` في Postman للاختبار السريع.

