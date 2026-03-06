<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'subscriptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'plan_id',
        'start_date',
        'end_date',
        'status',
        'price',
        'currency',
        'auto_renew',
        'cancel_at_period_end',
        'canceled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /**
     * Accessors to append when serializing (e.g. for API).
     *
     * @var array<int, string>
     */
    protected $appends = ['renewal_pending'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'price' => 'decimal:2',
            'auto_renew' => 'boolean',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * هل الاشتراك في فترة انتظار التجديد (انتهى end_date منذ مدة لا تتجاوز فترة السماح مع تفعيل التجديد التلقائي).
     */
    public function getRenewalPendingAttribute(): bool
    {
        if (!$this->auto_renew || !in_array((string) $this->status, ['active', 'past_due'], true)) {
            return false;
        }
        $endDate = $this->end_date;
        if (!$endDate) {
            return false;
        }
        $graceMinutes = (int) config('services.billing.renewal_grace_period_minutes', 15);
        $graceCutoff = now()->subMinutes($graceMinutes);
        return $endDate->lt(now()) && $endDate->gte($graceCutoff);
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\Users\User::class)->withTrashed();
    }

    /**
     * Get the plan that owns the subscription.
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    /**
     * Get the subscription events.
     */
    public function subscriptionEvents()
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    /**
     * Get the subscription discounts.
     */
    public function subscriptionDiscounts()
    {
        return $this->hasMany(SubscriptionDiscount::class);
    }

    /**
     * Get the user courses.
     */
    public function userCourses()
    {
        return $this->hasMany(\App\Models\Progress\UserCourse::class);
    }
}

