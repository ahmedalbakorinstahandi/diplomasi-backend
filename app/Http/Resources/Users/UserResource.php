<?php

namespace App\Http\Resources\Users;

use App\Http\Resources\Billing\SubscriptionResource;
use App\Http\Resources\Content\ArticleResource;
use App\Http\Resources\Progress\UserCourseResource;
use App\Http\Resources\Progress\UserLessonAttemptResource;
use App\Http\Resources\Progress\UserLessonProgressResource;
use App\Http\Resources\Progress\UserLevelProgressResource;
use App\Http\Resources\Scenarios\UserScenarioAttemptResource;
use App\Http\Resources\System\ActivityLogResource;
use App\Http\Resources\System\CertificateResource;
use App\Http\Resources\System\NotificationResource;
use App\Services\MediaUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone_verified' => $this->phone_verified,
            'email_verified' => $this->email_verified,
            'avatar' => MediaUrlService::toUrl($this->avatar),
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'language' => $this->language,
            'status' => $this->status,
            'is_guest' => (bool) $this->is_guest,
            'guest_converted_at' => $this->guest_converted_at,
            'registration_completed_at' => $this->registration_completed_at,
            'account_state' => $this->account_state,
            'approved' => false,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'user_roles' => UserRoleResource::collection($this->whenLoaded('userRoles')),
            'user_courses' => UserCourseResource::collection($this->whenLoaded('userCourses')),
            'user_lesson_progress' => UserLessonProgressResource::collection($this->whenLoaded('userLessonProgress')),
            'user_level_progress' => UserLevelProgressResource::collection($this->whenLoaded('userLevelProgress')),
            'user_lesson_attempts' => UserLessonAttemptResource::collection($this->whenLoaded('userLessonAttempts')),
            'user_scenario_attempts' => UserScenarioAttemptResource::collection($this->whenLoaded('userScenarioAttempts')),
            'subscriptions' => SubscriptionResource::collection($this->whenLoaded('subscriptions')),
            'notifications' => NotificationResource::collection($this->whenLoaded('notifications')),
            'certificates' => CertificateResource::collection($this->whenLoaded('certificates')),
            'activity_logs' => ActivityLogResource::collection($this->whenLoaded('activityLogs')),
            'articles' => ArticleResource::collection($this->whenLoaded('articles')),
        ];
    }
}

