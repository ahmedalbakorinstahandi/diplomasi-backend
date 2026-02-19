<?php

namespace App\Http\Services\Billing;

use App\Models\Billing\SavedPaymentMethod;

class PaymentMethodService
{
    public function listForUser(int $userId)
    {
        return SavedPaymentMethod::query()
            ->where('user_id', $userId)
            ->where('provider', 'moyasar')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();
    }

    public function storeForUser(int $userId, array $input): SavedPaymentMethod
    {
        $isDefault = (bool) ($input['is_default'] ?? false);

        if ($isDefault) {
            SavedPaymentMethod::query()
                ->where('user_id', $userId)
                ->where('provider', 'moyasar')
                ->update(['is_default' => false]);
        }

        $method = SavedPaymentMethod::query()->updateOrCreate(
            [
                'provider' => 'moyasar',
                'token' => $input['token'],
            ],
            [
                'user_id' => $userId,
                'status' => $input['status'] ?? 'active',
                'brand' => $input['brand'] ?? null,
                'last4' => $input['last4'] ?? null,
                'exp_month' => $input['exp_month'] ?? null,
                'exp_year' => $input['exp_year'] ?? null,
                'is_default' => $isDefault,
                'meta' => $input['meta'] ?? null,
            ]
        );

        if (!$method->is_default) {
            $hasDefault = SavedPaymentMethod::query()
                ->where('user_id', $userId)
                ->where('provider', 'moyasar')
                ->where('status', 'active')
                ->where('is_default', true)
                ->exists();

            if (!$hasDefault) {
                $method->update(['is_default' => true]);
            }
        }

        return $method->fresh();
    }
}

