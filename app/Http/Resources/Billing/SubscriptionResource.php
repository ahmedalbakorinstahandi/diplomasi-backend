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
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'price' => $this->price,
            'currency' => $this->currency,
            'auto_renew' => $this->auto_renew,
            'cancel_at_period_end' => (bool) ($this->cancel_at_period_end ?? false),
            'canceled_at' => $this->canceled_at,
            'geidea_subscription_id' => $this->geidea_subscription_id,
            'geidea_order_id' => $this->geidea_order_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'subscription_events' => SubscriptionEventResource::collection($this->whenLoaded('subscriptionEvents')),
            'subscription_discounts' => SubscriptionDiscountResource::collection($this->whenLoaded('subscriptionDiscounts')),
            'user_courses' => UserCourseResource::collection($this->whenLoaded('userCourses')),
        ];
    }
}

