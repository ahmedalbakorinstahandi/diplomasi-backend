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
        'phone_verified',
        'email_verified',
        'password',
        'language',
        'status',
        'otp',
        'otp_expire_at',
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

        // Get user from cache (stored by SetLocaleMiddleware)
        return cache()->get($cacheKey);
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
}
