<?php

namespace App\Http\Resources\Billing;

use App\Http\Resources\Users\UserResource;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
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
            'subscription_id' => $this->subscription_id,
            'payment_transaction_id' => $this->payment_transaction_id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'charged_amount_minor' => $this->whenLoaded('paymentTransaction', function () {
                return $this->paymentTransaction?->amount_minor;
            }),
            'charged_currency' => $this->whenLoaded('paymentTransaction', function () {
                return $this->paymentTransaction?->currency;
            }),
            'exchange_rate_usd_to_sar' => $this->whenLoaded('paymentTransaction', function () {
                return $this->paymentTransaction?->exchange_rate_usd_to_sar;
            }),
            'exchange_rate_at' => $this->whenLoaded('paymentTransaction', function () {
                return $this->paymentTransaction?->exchange_rate_at;
            }),
            'disclaimer_version' => $this->whenLoaded('paymentTransaction', function () {
                return $this->paymentTransaction?->disclaimer_version;
            }),
            'issued_at' => $this->issued_at,
            'due_at' => $this->due_at,
            'paid_at' => $this->paid_at,
            'pdf_path' => $this->pdf_path,
            'pdf_url' => $this->pdf_path ? FileService::fileUrl((string) $this->pdf_path) : null,
            'meta' => $this->meta,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'subscription' => $this->whenLoaded('subscription', fn () => new SubscriptionResource($this->subscription)),
            'payment_transaction' => $this->whenLoaded('paymentTransaction', function () {
                return [
                    'id' => $this->paymentTransaction?->id,
                    'merchant_reference_id' => $this->paymentTransaction?->merchant_reference_id,
                    'provider_payment_id' => $this->paymentTransaction?->provider_payment_id,
                    'status' => $this->paymentTransaction?->status,
                    'gateway_status' => $this->paymentTransaction?->gateway_status,
                ];
            }),
        ];
    }
}

