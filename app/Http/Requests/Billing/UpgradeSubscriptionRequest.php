<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class UpgradeSubscriptionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:plans,id',
            // ============================================================
            // 🔴 TODO: إضافة validation لطريقة الدفع إذا لزم الأمر
            // ============================================================
            // 'payment_method_id' => 'required|string', // للـ Stripe Payment Method
            // أو
            // 'payment_intent_id' => 'required|string', // إذا تم إنشاء Payment Intent مسبقاً
            // ============================================================
        ];
    }
}