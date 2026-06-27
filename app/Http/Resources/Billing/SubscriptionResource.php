<?php

namespace App\Http\Resources\Billing;

use App\Http\Resources\Progress\UserCourseResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
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
            'user_id' => $this->user_id,
            'plan_id' => $this->plan_id,
            'provider' => $this->resolveProvider(),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'price' => $this->price,
            'currency' => $this->currency,
            'auto_renew' => $this->auto_renew,
            'cancel_at_period_end' => (bool) ($this->cancel_at_period_end ?? false),
            'canceled_at' => $this->canceled_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships (never wrap MissingValue in JsonResource — use whenLoaded + closure)
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'plan' => $this->whenLoaded('plan', fn () => new PlanResource($this->plan)),
            'subscription_events' => $this->whenLoaded('subscriptionEvents', fn () => SubscriptionEventResource::collection($this->subscriptionEvents)),
            'subscription_discounts' => $this->whenLoaded('subscriptionDiscounts', fn () => SubscriptionDiscountResource::collection($this->subscriptionDiscounts)),
            'user_courses' => $this->whenLoaded('userCourses', fn () => UserCourseResource::collection($this->userCourses)),
        ];
    }
}

