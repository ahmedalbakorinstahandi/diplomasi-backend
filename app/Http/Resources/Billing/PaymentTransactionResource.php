<?php

namespace App\Http\Resources\Billing;

use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentTransactionResource extends JsonResource
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
            'subscription_id' => $this->subscription_id,
            'merchant_reference_id' => $this->merchant_reference_id,
            'given_id' => $this->given_id,
            'provider' => $this->provider,
            'provider_payment_id' => $this->provider_payment_id,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'display_currency' => $this->display_currency,
            'display_amount_minor' => $this->display_amount_minor,
            'exchange_rate_usd_to_sar' => $this->exchange_rate_usd_to_sar,
            'exchange_rate_at' => $this->exchange_rate_at,
            'exchange_rate_source' => $this->exchange_rate_source,
            'disclaimer_version' => $this->disclaimer_version,
            'expires_at' => $this->expires_at,
            'attempt_no' => $this->attempt_no,
            'billing_period_start' => $this->billing_period_start,
            'billing_period_end' => $this->billing_period_end,
            'next_retry_at' => $this->next_retry_at,
            'status' => $this->status,
            'gateway_status' => $this->gateway_status,
            'redirect_url' => $this->redirect_url,
            'finalized_at' => $this->finalized_at,
            'verified_at' => $this->verified_at,
            'last_error_code' => $this->last_error_code,
            'last_error_message' => $this->last_error_message,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'plan' => $this->whenLoaded('plan', fn () => new PlanResource($this->plan)),
            'invoice' => $this->whenLoaded('invoice', function () {
                return [
                    'id' => $this->invoice?->id,
                    'invoice_number' => $this->invoice?->invoice_number,
                    'status' => $this->invoice?->status,
                ];
            }),
        ];
    }
}

