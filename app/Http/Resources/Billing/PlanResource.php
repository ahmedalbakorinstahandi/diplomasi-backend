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
        $isIos = strtolower((string) ($request->query('platform') ?? $request->input('platform') ?? '')) === 'ios';
        $price = $this->price;
        if ($isIos && $this->ios_price !== null && $this->ios_price !== '') {
            $price = $this->ios_price;
        }

        $out = [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $price,
            'interval' => $this->interval,
            'interval_label' => $this->interval_label,
            'description' => $this->description,
            'caption' => $this->caption,
            'is_featured' => (bool) $this->is_featured,
            'icon_url' => MediaUrlService::toUrl($this->icon_url),
            'features' => $this->features,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'subscriptions' => SubscriptionResource::collection($this->whenLoaded('subscriptions')),
            'subscription_events' => SubscriptionEventResource::collection($this->whenLoaded('subscriptionEvents')),
        ];

        if ($isIos && $this->ios_product_id !== null && $this->ios_product_id !== '') {
            $out['ios_product_id'] = $this->ios_product_id;
        }

        return $out;
    }
}
