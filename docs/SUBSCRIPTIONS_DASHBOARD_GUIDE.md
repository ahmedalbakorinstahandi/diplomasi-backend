# دليل إدارة الاشتراكات في الداشبورد

## نظرة عامة

هذا الدليل يشرح بالتفصيل كيفية إدارة الاشتراكات من لوحة التحكم (Dashboard) في Diplomasi Backend. يتضمن إدارة الاشتراكات، التقارير المالية، والمعاملات.

## المتطلبات

### 1. الصلاحيات المطلوبة
- `subscriptions.view` - عرض الاشتراكات
- `subscriptions.create` - إنشاء اشتراكات
- `subscriptions.update` - تحديث الاشتراكات
- `subscriptions.delete` - حذف الاشتراكات
- `subscriptions.manage` - إدارة الاشتراكات (pause, resume, extend)

### 2. Authentication
جميع الـ endpoints تتطلب:
- Bearer Token في Header
- `X-Context: dashboard` header
- صلاحيات Admin

## البنية العامة

### تدفق العمل

```
Admin Dashboard
   ↓
API Request (with Admin Token)
   ↓
Permission Check
   ↓
Service Layer
   ↓
Database/Stripe
   ↓
Response with Financial Transparency
```

## API Endpoints

### Base URL
```
https://your-api-domain.com/api/v1/admin
```

### Headers
```
Authorization: Bearer {admin_token}
X-Context: dashboard
Accept-Language: ar
Content-Type: application/json
```

## 1. عرض قائمة الاشتراكات

### Endpoint
```
GET /api/v1/admin/subscriptions
```

### Query Parameters
```
?page=1
&per_page=20
&status=active
&user_id=123
&plan_id=1
&sort_field=created_at
&sort_order=desc
&start_date=2026-01-01
&end_date=2026-12-31
```

### Request Example
```javascript
const response = await fetch('/api/v1/admin/subscriptions?status=active&per_page=20', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'X-Context': 'dashboard',
    'Accept-Language': 'ar',
  },
});
```

### Response
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 123,
      "user": {
        "id": 123,
        "first_name": "أحمد",
        "last_name": "محمد",
        "email": "ahmed@example.com"
      },
      "plan_id": 1,
      "plan": {
        "id": 1,
        "name": "Basic Plan",
        "price": 9.99,
        "interval": "monthly"
      },
      "start_date": "2026-01-01",
      "end_date": "2026-02-01",
      "status": "active",
      "price": 9.99,
      "currency": "USD",
      "auto_renew": true,
      "cancel_at_period_end": false,
      "stripe_subscription_id": "sub_1234",
      "current_period_start": "2026-01-01",
      "current_period_end": "2026-02-01",
      "created_at": "2026-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 100,
    "last_page": 5
  }
}
```

### Implementation Example (React)
```jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

function SubscriptionsList() {
  const [subscriptions, setSubscriptions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({
    status: 'active',
    per_page: 20,
    page: 1,
  });

  useEffect(() => {
    loadSubscriptions();
  }, [filters]);

  const loadSubscriptions = async () => {
    try {
      setLoading(true);
      const response = await axios.get('/api/v1/admin/subscriptions', {
        params: filters,
        headers: {
          'Authorization': `Bearer ${getToken()}`,
          'X-Context': 'dashboard',
        },
      });
      setSubscriptions(response.data.data);
    } catch (error) {
      console.error('Error loading subscriptions:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <Filters filters={filters} onChange={setFilters} />
      <Table data={subscriptions} loading={loading} />
    </div>
  );
}
```

## 2. عرض تفاصيل اشتراك

### Endpoint
```
GET /api/v1/admin/subscriptions/{id}
```

### Response
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 123,
    "user": { /* user details */ },
    "plan_id": 1,
    "plan": { /* plan details */ },
    "start_date": "2026-01-01",
    "end_date": "2026-02-01",
    "status": "active",
    "price": 9.99,
    "currency": "USD",
    "auto_renew": true,
    "cancel_at_period_end": false,
    "stripe_subscription_id": "sub_1234",
    "stripe_customer_id": "cus_1234",
    "current_period_start": "2026-01-01",
    "current_period_end": "2026-02-01",
    "subscription_events": [
      {
        "id": 1,
        "event_type": "created",
        "amount_charged": 9.99,
        "created_at": "2026-01-01T00:00:00Z"
      }
    ],
    "created_at": "2026-01-01T00:00:00Z"
  }
}
```

## 3. إنشاء اشتراك (Admin)

### Endpoint
```
POST /api/v1/admin/subscriptions
```

### Request Body
```json
{
  "user_id": 123,
  "plan_id": 1,
  "stripe_subscription_id": "sub_1234",
  "auto_renew": true
}
```

### Response
```json
{
  "success": true,
  "message": "messages.subscription.created",
  "data": { /* subscription object */ }
}
```

### ملاحظات
- يمكن إنشاء اشتراك بدون `stripe_subscription_id` (للاستخدام الداخلي)
- `user_id` مطلوب
- `plan_id` مطلوب

## 4. توقيف الاشتراك (Pause)

### Endpoint
```
POST /api/v1/admin/subscriptions/{id}/pause
```

### Request Body
```json
{
  "reason": "User requested pause"
}
```

### Response
```json
{
  "success": true,
  "message": "Subscription paused",
  "data": {
    "id": 1,
    "status": "cancelled",
    /* ... other fields ... */
  }
}
```

### كيف يعمل
1. يتم تحديث حالة الاشتراك إلى `cancelled`
2. يتم تسجيل معاملة مالية من نوع `admin_adjustment` مع `amount: 0`
3. يتم حفظ السبب في `metadata`
4. يتم تسجيل `admin_id` في `metadata` للشفافية

### Financial Transaction Record
```json
{
  "type": "admin_adjustment",
  "amount": 0,
  "status": "completed",
  "description": "Subscription paused by admin. Reason: User requested pause",
  "metadata": {
    "action": "pause",
    "old_status": "active",
    "new_status": "cancelled",
    "reason": "User requested pause",
    "admin_id": 1
  }
}
```

## 5. استئناف الاشتراك (Resume)

### Endpoint
```
POST /api/v1/admin/subscriptions/{id}/resume
```

### Response
```json
{
  "success": true,
  "message": "Subscription resumed",
  "data": { /* subscription object with status: active */ }
}
```

### كيف يعمل
1. يتم تحديث حالة الاشتراك إلى `active`
2. يتم تسجيل معاملة مالية من نوع `admin_adjustment`
3. يتم حفظ تفاصيل العملية في `metadata`

## 6. التجديد اليدوي (Manual Renewal)

### Endpoint
```
POST /api/v1/admin/subscriptions/{id}/renew-manual
```

### Request Body
```json
{
  "days": 30
}
```

### Response
```json
{
  "success": true,
  "message": "Subscription renewed manually",
  "data": {
    "id": 1,
    "end_date": "2026-03-01", // تم تمديده 30 يوم
    "status": "active",
    /* ... */
  }
}
```

### كيف يعمل
1. يتم تمديد `end_date` بالعدد المحدد من الأيام
2. إذا لم يتم تحديد `days`، يتم استخدام فترة الخطة الافتراضية
3. يتم إنشاء `SubscriptionEvent` من نوع `renewed`
4. يتم تسجيل معاملة مالية مع `amount: 0` (لا يوجد دفع)
5. يتم حفظ `admin_id` و `days_added` في `metadata`

### Financial Transaction Record
```json
{
  "type": "admin_adjustment",
  "amount": 0,
  "status": "completed",
  "description": "Subscription manually renewed by admin. Extended by 30 days.",
  "metadata": {
    "action": "renew_manual",
    "old_end_date": "2026-02-01",
    "new_end_date": "2026-03-01",
    "days_added": 30,
    "admin_id": 1
  }
}
```

## 7. تمديد الاشتراك (Extend)

### Endpoint
```
POST /api/v1/admin/subscriptions/{id}/extend
```

### Request Body
```json
{
  "days": 15
}
```

### Response
```json
{
  "success": true,
  "message": "Subscription extended",
  "data": {
    "id": 1,
    "end_date": "2026-02-16", // تم تمديده 15 يوم
    /* ... */
  }
}
```

### الفرق بين Extend و Renew Manual
- **Renew Manual**: يضيف فترة كاملة (عادة فترة الخطة)
- **Extend**: يضيف عدد أيام محدد فقط

### Financial Transaction Record
```json
{
  "type": "admin_adjustment",
  "amount": 0,
  "status": "completed",
  "description": "Subscription extended by admin. Extended by 15 days.",
  "metadata": {
    "action": "extend",
    "old_end_date": "2026-02-01",
    "new_end_date": "2026-02-16",
    "days_added": 15,
    "admin_id": 1
  }
}
```

## 8. التقارير المالية

### 8.1 نظرة عامة مالية

#### Endpoint
```
GET /api/v1/admin/financial/overview
```

#### Query Parameters
```
?start_date=2026-01-01
&end_date=2026-12-31
```

#### Response
```json
{
  "success": true,
  "data": {
    "total_revenue": 10000.00,
    "total_upgrades": 500.00,
    "total_refunds": 100.00,
    "net_revenue": 10400.00,
    "total_transactions": 500,
    "pending_transactions": 5,
    "failed_transactions": 10
  }
}
```

### 8.2 تفصيل الإيرادات

#### Endpoint
```
GET /api/v1/admin/financial/revenue
```

#### Query Parameters
```
?start_date=2026-01-01
&end_date=2026-12-31
&group_by=day|week|month|year
```

#### Response
```json
{
  "success": true,
  "data": [
    {
      "period": "2026-01-01",
      "revenue": 500.00,
      "transactions": 10
    },
    {
      "period": "2026-01-02",
      "revenue": 750.00,
      "transactions": 15
    }
  ]
}
```

### 8.3 قائمة المعاملات المالية

#### Endpoint
```
GET /api/v1/admin/financial/transactions
```

#### Query Parameters
```
?page=1
&per_page=20
&type=subscription_payment|upgrade_payment|refund|admin_adjustment
&status=completed|pending|failed
&user_id=123
&subscription_id=1
&start_date=2026-01-01
&end_date=2026-12-31
```

#### Response
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "subscription_id": 1,
      "user_id": 123,
      "user": { /* user details */ },
      "type": "subscription_payment",
      "amount": 9.99,
      "currency": "USD",
      "status": "completed",
      "stripe_payment_intent_id": "pi_1234",
      "stripe_invoice_id": "in_1234",
      "description": "Subscription payment for plan: Basic Plan",
      "metadata": {},
      "processed_at": "2026-01-01T00:00:00Z",
      "created_at": "2026-01-01T00:00:00Z"
    },
    {
      "id": 2,
      "type": "admin_adjustment",
      "amount": 0,
      "status": "completed",
      "description": "Subscription paused by admin. Reason: User requested pause",
      "metadata": {
        "action": "pause",
        "admin_id": 1
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 100
  }
}
```

### 8.4 إحصائيات الاشتراكات

#### Endpoint
```
GET /api/v1/admin/financial/subscriptions-stats
```

#### Response
```json
{
  "success": true,
  "data": {
    "active_subscriptions": 500,
    "expired_subscriptions": 100,
    "cancelled_subscriptions": 50,
    "total_subscriptions": 650,
    "monthly_revenue": 5000.00
  }
}
```

## 9. الشفافية المالية

### 9.1 مبدأ الشفافية

جميع العمليات الإدارية (pause, resume, extend, renew-manual) يتم تسجيلها في `financial_transactions` مع:
- `type: "admin_adjustment"`
- `amount: 0` (لا يوجد دفع فعلي)
- `metadata` يحتوي على تفاصيل العملية
- `admin_id` للشفافية

### 9.2 مثال: تتبع التغييرات

```javascript
// عرض جميع التعديلات الإدارية
const adminAdjustments = await fetch('/api/v1/admin/financial/transactions?type=admin_adjustment', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'X-Context': 'dashboard',
  },
});

// النتيجة
[
  {
    "id": 1,
    "type": "admin_adjustment",
    "amount": 0,
    "description": "Subscription paused by admin",
    "metadata": {
      "action": "pause",
      "admin_id": 1,
      "admin_name": "Admin User",
      "reason": "User requested"
    },
    "created_at": "2026-01-15T10:00:00Z"
  },
  {
    "id": 2,
    "type": "admin_adjustment",
    "amount": 0,
    "description": "Subscription extended by admin. Extended by 30 days.",
    "metadata": {
      "action": "extend",
      "admin_id": 1,
      "days_added": 30
    },
    "created_at": "2026-01-20T14:00:00Z"
  }
]
```

## 10. Dashboard UI Components

### 10.1 Subscriptions Table Component

```jsx
function SubscriptionsTable({ subscriptions, onAction }) {
  return (
    <Table>
      <thead>
        <tr>
          <th>User</th>
          <th>Plan</th>
          <th>Status</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Auto Renew</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        {subscriptions.map(sub => (
          <tr key={sub.id}>
            <td>{sub.user.first_name} {sub.user.last_name}</td>
            <td>{sub.plan.name}</td>
            <td>
              <Badge status={sub.status}>
                {sub.status}
              </Badge>
            </td>
            <td>{sub.start_date}</td>
            <td>{sub.end_date}</td>
            <td>{sub.auto_renew ? 'Yes' : 'No'}</td>
            <td>
              <ActionsMenu
                subscription={sub}
                onPause={() => onAction('pause', sub.id)}
                onResume={() => onAction('resume', sub.id)}
                onExtend={() => onAction('extend', sub.id)}
                onRenew={() => onAction('renew', sub.id)}
              />
            </td>
          </tr>
        ))}
      </tbody>
    </Table>
  );
}
```

### 10.2 Financial Overview Dashboard

```jsx
function FinancialOverview() {
  const [overview, setOverview] = useState(null);
  const [dateRange, setDateRange] = useState({
    start_date: startOfMonth(new Date()),
    end_date: endOfMonth(new Date()),
  });

  useEffect(() => {
    loadOverview();
  }, [dateRange]);

  const loadOverview = async () => {
    const response = await axios.get('/api/v1/admin/financial/overview', {
      params: dateRange,
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'X-Context': 'dashboard',
      },
    });
    setOverview(response.data.data);
  };

  return (
    <div className="financial-overview">
      <DateRangePicker value={dateRange} onChange={setDateRange} />
      <div className="stats-grid">
        <StatCard
          title="Total Revenue"
          value={overview?.total_revenue}
          currency="USD"
        />
        <StatCard
          title="Net Revenue"
          value={overview?.net_revenue}
          currency="USD"
        />
        <StatCard
          title="Total Transactions"
          value={overview?.total_transactions}
        />
        <StatCard
          title="Pending Transactions"
          value={overview?.pending_transactions}
          variant="warning"
        />
      </div>
    </div>
  );
}
```

### 10.3 Admin Actions Modal

```jsx
function AdminActionModal({ subscription, action, onClose, onConfirm }) {
  const [reason, setReason] = useState('');
  const [days, setDays] = useState(30);

  const handleSubmit = async () => {
    const payload = {};
    
    if (action === 'pause') {
      payload.reason = reason;
    } else if (action === 'extend' || action === 'renew') {
      payload.days = days;
    }

    await onConfirm(action, subscription.id, payload);
    onClose();
  };

  return (
    <Modal onClose={onClose}>
      <h2>
        {action === 'pause' && 'Pause Subscription'}
        {action === 'resume' && 'Resume Subscription'}
        {action === 'extend' && 'Extend Subscription'}
        {action === 'renew' && 'Renew Subscription'}
      </h2>
      
      {action === 'pause' && (
        <div>
          <label>Reason (optional)</label>
          <textarea
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="Enter reason for pausing..."
          />
        </div>
      )}
      
      {(action === 'extend' || action === 'renew') && (
        <div>
          <label>Days to add</label>
          <input
            type="number"
            value={days}
            onChange={(e) => setDays(parseInt(e.target.value))}
            min="1"
          />
        </div>
      )}
      
      <div className="modal-actions">
        <button onClick={onClose}>Cancel</button>
        <button onClick={handleSubmit} className="primary">
          Confirm
        </button>
      </div>
    </Modal>
  );
}
```

## 11. Error Handling

### Error Response Format
```json
{
  "success": false,
  "message": "Error message in Arabic",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

### Implementation
```javascript
async function handleAdminAction(action, subscriptionId, payload) {
  try {
    const response = await axios.post(
      `/api/v1/admin/subscriptions/${subscriptionId}/${action}`,
      payload,
      {
        headers: {
          'Authorization': `Bearer ${getToken()}`,
          'X-Context': 'dashboard',
        },
      }
    );
    
    if (response.data.success) {
      showSuccessMessage(response.data.message);
      return response.data.data;
    }
  } catch (error) {
    if (error.response) {
      const errorData = error.response.data;
      showErrorMessage(errorData.message || 'An error occurred');
    } else {
      showErrorMessage('Network error. Please try again.');
    }
    throw error;
  }
}
```

## 12. Best Practices

### 12.1 Always Log Admin Actions
```javascript
// في كل action إداري، يتم تسجيل:
// 1. Financial Transaction
// 2. Activity Log (إن وجد)
// 3. Metadata مع admin_id
```

### 12.2 Validate Before Actions
```javascript
// قبل pause/resume/extend
if (subscription.status === 'expired') {
  showWarning('Subscription is already expired');
  return;
}
```

### 12.3 Show Confirmation Dialogs
```javascript
// دائماً اطلب تأكيد قبل Actions الحرجة
const confirmed = await showConfirmDialog({
  title: 'Pause Subscription',
  message: 'Are you sure you want to pause this subscription?',
  confirmText: 'Pause',
  cancelText: 'Cancel',
});
```

### 12.4 Refresh Data After Actions
```javascript
// بعد أي action، قم بتحديث البيانات
await handleAdminAction('pause', subscriptionId, { reason });
await loadSubscriptions(); // Refresh list
```

## 13. Security Considerations

### 13.1 Permission Checks
```javascript
// Backend يتحقق من الصلاحيات تلقائياً
// Frontend يجب أن يتحقق أيضاً للـ UX
const canPause = userPermissions.includes('subscriptions.manage');
const canExtend = userPermissions.includes('subscriptions.manage');
```

### 13.2 Audit Trail
```javascript
// جميع Actions يتم تسجيلها في:
// 1. financial_transactions (مع admin_id)
// 2. subscription_events
// 3. activity_logs (إن وجد)
```

## 14. Testing

### Test Scenarios

1. **Pause Subscription**
   - Pause active subscription
   - Verify status changed to cancelled
   - Verify financial transaction created
   - Verify metadata contains admin_id

2. **Resume Subscription**
   - Resume paused subscription
   - Verify status changed to active
   - Verify financial transaction created

3. **Extend Subscription**
   - Extend by 15 days
   - Verify end_date updated
   - Verify financial transaction created

4. **Manual Renewal**
   - Renew without days (use plan interval)
   - Renew with custom days
   - Verify subscription event created

5. **Financial Reports**
   - Test overview with date range
   - Test revenue breakdown
   - Test transactions filtering
   - Test subscription stats

## 15. Troubleshooting

### مشكلة: Permission denied
**الحل**: تأكد من:
- Token صالح
- User لديه الصلاحيات المطلوبة
- `X-Context: dashboard` header موجود

### مشكلة: Financial transaction not created
**الحل**: 
- تحقق من logs
- تأكد من أن Service يتم استدعاؤه بشكل صحيح

### مشكلة: Subscription status not updating
**الحل**:
- تحقق من Stripe webhook configuration
- تحقق من scheduled commands
- راجع logs للـ errors

## 16. Resources

- [Stripe Dashboard](https://dashboard.stripe.com)
- [API Documentation](./API_ENDPOINTS_TESTING.md)
- [Flutter Integration Guide](./SUBSCRIPTIONS_FLUTTER_GUIDE.md)

## 17. Support

للدعم الفني، يرجى التواصل مع فريق التطوير.
