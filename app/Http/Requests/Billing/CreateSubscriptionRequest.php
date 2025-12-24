<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class CreateSubscriptionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:plans,id',
            'auto_renew' => 'nullable|boolean',
            'user_id' => 'nullable|exists:users,id', // اختياري - يمكن أخذه من authenticated user
            // ============================================================
            // باقي الحقول (price, currency, dates, status) 
            // يتم حسابها تلقائياً على السيرفر من الخطة
            // ============================================================
        ];
    }
}
