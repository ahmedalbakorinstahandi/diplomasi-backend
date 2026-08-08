<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'avatar',
        'email',
        'phone',
        'address',
        'phone_verified',
        'email_verified',
        'password',
        'language',
        'status',
        'is_guest',
        'guest_converted_at',
        'registration_completed_at',
        'guest_last_active_at',
        'otp',
        'otp_expire_at',
        'last_activity_at',
        'last_opened_app_at',
        'is_active',
        'inactive_since_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'otp_expire_at' => 'datetime',
            'email_verified' => 'boolean',
            'phone_verified' => 'boolean',
            'is_guest' => 'boolean',
            'guest_converted_at' => 'datetime',
            'registration_completed_at' => 'datetime',
            'guest_last_active_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'last_opened_app_at' => 'datetime',
            'is_active' => 'boolean',
            'inactive_since_at' => 'datetime',
        ];
    }


    public function getAvatarUrlAttribute()
    {
        if (empty($this->avatar) || is_null($this->avatar) || !isset($this->avatar)) {
            $name = urlencode($this->first_name . ' ' . $this->last_name);
            // Generate consistent color based on user name
            $colors = ['f44336', 'e91e63', '9c27b0', '673ab7', '3f51b5', '2196f3', '03a9f4', '00bcd4', '009688', '4caf50', '8bc34a', 'cddc39', 'ffeb3b', 'ffc107', 'ff9800', 'ff5722'];
            $colorIndex = abs(crc32($this->first_name . $this->last_name)) % count($colors);
            $backgroundColor = $colors[$colorIndex];

            return "https://ui-avatars.com/api/?name={$name}&size=256&background={$backgroundColor}&color=fff";
        } else {
            return asset('storage/' . $this->avatar);
        }
    }

    public static function auth()
    {
        // Check if user is authenticated
        if (!Auth::guard('sanctum')->check()) {
            return null;
        }

        $token = request()->bearerToken();
        if (!$token) {
            return null;
        }

        $cacheKey = 'request_user_' . $token;

        $cached = cache()->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $user = Auth::guard('sanctum')->user();
        if ($user instanceof self) {
            cache()->put($cacheKey, $user, 300);
            return $user;
        }

        return null;
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Check if user is admin (has super_admin role).
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Check if user has a specific permission through their roles.
     */
    public function hasPermission(string $permissionName): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->exists();
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissions) {
                $query->whereIn('name', $permissions);
            })
            ->exists();
    }

    /**
     * Get all effective permission names (merged across all roles).
     *
     * @return array<int, string>
     */
    public function getEffectivePermissionNames(): array
    {
        // Super admin implicitly has all permissions.
        if ($this->hasRole('super_admin')) {
            return Permission::query()->pluck('name')->values()->all();
        }

        $this->loadMissing(['roles.permissions']);

        return $this->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isGuest(): bool
    {
        return (bool) $this->is_guest;
    }

    public function getAccountStateAttribute(): string
    {
        if ($this->isGuest()) {
            return 'guest';
        }

        if (!(bool) $this->email_verified) {
            return 'registered_unverified';
        }

        return 'registered_verified';
    }

    public function canIssueCertificate(): bool
    {
        return !$this->isGuest() && (bool) $this->email_verified;
    }

    public function canSubscribe(): bool
    {
        return !$this->isGuest() && (bool) $this->email_verified;
    }

    /**
     * Get the roles for the user.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }

    /**
     * Get the user roles pivot records.
     */
    public function userRoles()
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * Get the user courses.
     */
    public function userCourses()
    {
        return $this->hasMany(\App\Models\Progress\UserCourse::class);
    }

    /**
     * Get the user lesson progress.
     */
    public function userLessonProgress()
    {
        return $this->hasMany(\App\Models\Progress\UserLessonProgress::class);
    }

    /**
     * Get the user level progress.
     */
    public function userLevelProgress()
    {
        return $this->hasMany(\App\Models\Progress\UserLevelProgress::class);
    }

    /**
     * Get the user lesson attempts.
     */
    public function userLessonAttempts()
    {
        return $this->hasMany(\App\Models\Progress\UserLessonAttempt::class);
    }

    /**
     * Get the user scenario attempts.
     */
    public function userScenarioAttempts()
    {
        return $this->hasMany(\App\Models\Scenarios\UserScenarioAttempt::class);
    }

    /**
     * Get the subscriptions.
     */
    public function subscriptions()
    {
        return $this->hasMany(\App\Models\Billing\Subscription::class);
    }

    /**
     * Check if user has an active subscription.
     */
    public function isSubscribed(): bool
    {
        return $this->hasActiveSubscription();
    }

    /**
     * Check if user has an active subscription.
     * Includes grace period: if end_date passed but within renewal window (e.g. 15 min) and auto_renew, still considered active.
     */
    public function hasActiveSubscription(): bool
    {
        $graceMinutes = (int) config('services.billing.renewal_grace_period_minutes', 15);
        $graceCutoff = now()->subMinutes($graceMinutes);

        return $this->subscriptions()
            ->whereIn('status', ['active', 'past_due'])
            ->where(function ($query) use ($graceMinutes, $graceCutoff) {
                $query->where('end_date', '>=', now())
                    ->orWhere(function ($q) use ($graceCutoff) {
                        $q->where('auto_renew', true)
                            ->where('end_date', '>=', $graceCutoff);
                    });
            })
            ->exists();
    }

    /**
     * Get the active subscription.
     * Uses same grace period as hasActiveSubscription.
     */
    public function getActiveSubscription()
    {
        $graceMinutes = (int) config('services.billing.renewal_grace_period_minutes', 15);
        $graceCutoff = now()->subMinutes($graceMinutes);

        return $this->subscriptions()
            ->whereIn('status', ['active', 'past_due'])
            ->where(function ($query) use ($graceCutoff) {
                $query->where('end_date', '>=', now())
                    ->orWhere(function ($q) use ($graceCutoff) {
                        $q->where('auto_renew', true)
                            ->where('end_date', '>=', $graceCutoff);
                    });
            })
            ->with(['plan'])
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get the notifications.
     */
    public function notifications()
    {
        return $this->hasMany(\App\Models\System\Notification::class);
    }

    /**
     * Get the certificates.
     */
    public function certificates()
    {
        return $this->hasMany(\App\Models\System\Certificate::class);
    }

    /**
     * Get the activity logs.
     */
    public function activityLogs()
    {
        return $this->hasMany(\App\Models\System\ActivityLog::class);
    }

    /**
     * Get the articles authored by the user.
     */
    public function articles()
    {
        return $this->hasMany(\App\Models\Content\Article::class, 'author_id');
    }

    public function userPodcastProgress()
    {
        return $this->hasMany(\App\Models\Progress\UserPodcastProgress::class);
    }

    public function userPodcastFavorites()
    {
        return $this->hasMany(\App\Models\Progress\UserPodcastFavorite::class);
    }

    /**
     * Get the AI Negotiator sessions for the user.
     */
    public function aiNegotiatorSessions()
    {
        return $this->hasMany(\App\Models\AiNegotiator\AiNegotiatorSession::class);
    }

    /**
     * Get the AI Negotiator credit balance for the user.
     */
    public function aiNegotiatorCredit()
    {
        return $this->hasOne(\App\Models\AiNegotiator\AiNegotiatorUserCredit::class);
    }

    /**
     * Get the AI Negotiator credit transactions for the user.
     */
    public function aiNegotiatorCreditTransactions()
    {
        return $this->hasMany(\App\Models\AiNegotiator\AiNegotiatorCreditTransaction::class);
    }
}
