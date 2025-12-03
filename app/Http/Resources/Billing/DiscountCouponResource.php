<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountCouponResource extends JsonResource
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
            'code' => $this->code,
            'description' => $this->description,
            'percentage' => $this->percentage,
            'max_uses' => $this->max_uses,
            'max_uses_by_user' => $this->max_uses_by_user,
            'used_count' => $this->used_count,
            'expires_at' => $this->expires_at,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'subscription_discounts' => SubscriptionDiscountResource::collection($this->whenLoaded('subscriptionDiscounts')),
        ];
    }
}

