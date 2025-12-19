<?php

namespace App\Http\Resources\Billing;

use App\Services\MediaUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
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
            'name' => $this->name,
            'stripe_plan_id' => $this->stripe_plan_id,
            'price' => $this->price,
            'interval' => $this->interval,
            'description' => $this->description,
            'icon_url' => MediaUrlService::toUrl($this->icon_url),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'subscriptions' => SubscriptionResource::collection($this->whenLoaded('subscriptions')),
            'subscription_events' => SubscriptionEventResource::collection($this->whenLoaded('subscriptionEvents')),
        ];
    }
}

