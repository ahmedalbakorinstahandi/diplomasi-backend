<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionEventResource extends JsonResource
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
            'event_type' => $this->event_type,
            'plan_id' => $this->plan_id,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'plan_price' => $this->plan_price,
            'amount_charged' => $this->amount_charged,
            'amount_refunded' => $this->amount_refunded,
            'currency' => $this->currency,
            'meta' => $this->meta,
            'created_at' => $this->created_at,
            
            // Relationships
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
            'plan' => new PlanResource($this->whenLoaded('plan')),
        ];
    }
}

