<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionDiscountResource extends JsonResource
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
            'subscription_id' => $this->subscription_id,
            'discount_id' => $this->discount_id,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'applied_at' => $this->applied_at,
            'created_at' => $this->created_at,
            
            // Relationships
            'subscription' => $this->whenLoaded('subscription', fn () => new SubscriptionResource($this->subscription)),
            'discount_coupon' => $this->whenLoaded('discountCoupon', fn () => new DiscountCouponResource($this->discountCoupon)),
        ];
    }
}

