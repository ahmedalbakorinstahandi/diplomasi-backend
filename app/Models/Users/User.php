<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

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

