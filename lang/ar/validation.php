<?php

return [

    /*
    |--------------------------------------------------------------------------
    | رسائل التحقق
    |--------------------------------------------------------------------------
    |
    | السطور التالية تحتوي على الرسائل الافتراضية التي يستخدمها محقق البيانات.
    | يمكن تخصيص هذه الرسائل لتتناسب مع متطلبات مشروعك.
    |
    */

    'accepted' => 'يجب قبول الحقل :attribute.',
    'accepted_if' => 'يجب قبول الحقل :attribute عندما يكون :other هو :value.',
    'active_url' => 'الحقل :attribute يجب أن يكون رابطاً صحيحاً.',
    'after' => 'الحقل :attribute يجب أن يكون تاريخاً بعد :date.',
    'after_or_equal' => 'الحقل :attribute يجب أن يكون تاريخاً بعد أو يساوي :date.',
    'alpha' => 'الحقل :attribute يجب أن يحتوي على أحرف فقط.',
    'alpha_dash' => 'الحقل :attribute يجب أن يحتوي فقط على أحرف، أرقام، شرطات وشرطات سفلية.',
    'alpha_num' => 'الحقل :attribute يجب أن يحتوي فقط على أحرف وأرقام.',
    'array' => 'الحقل :attribute يجب أن يكون مصفوفة.',
    'ascii' => 'الحقل :attribute يجب أن يحتوي على رموز وحروف إنجليزية فقط.',
    'before' => 'الحقل :attribute يجب أن يكون تاريخاً قبل :date.',
    'before_or_equal' => 'الحقل :attribute يجب أن يكون تاريخاً قبل أو يساوي :date.',
    'between' => [
        'array' => 'الحقل :attribute يجب أن يحتوي على :min إلى :max عناصر.',
        'file' => 'الحقل :attribute يجب أن يكون بين :min و :max كيلوبايت.',
        'numeric' => 'الحقل :attribute يجب أن يكون بين :min و :max.',
        'string' => 'الحقل :attribute يجب أن يكون بين :min و :max أحرف.',
    ],
    'boolean' => 'الحقل :attribute يجب أن يكون صحيحاً أو خاطئاً.',
    'can' => 'الحقل :attribute يحتوي على قيمة غير مصرح بها.',
    'confirmed' => 'تأكيد الحقل :attribute غير متطابق.',
    'contains' => 'الحقل :attribute يفتقد إلى قيمة مطلوبة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => 'الحقل :attribute يجب أن يكون تاريخاً صحيحاً.',
    'date_equals' => 'الحقل :attribute يجب أن يكون تاريخاً مساوياً لـ :date.',
    'date_format' => 'الحقل :attribute يجب أن يطابق التنسيق :format.',
    'decimal' => 'الحقل :attribute يجب أن يحتوي على :decimal منازل عشرية.',
    'declined' => 'يجب رفض الحقل :attribute.',
    'declined_if' => 'يجب رفض الحقل :attribute عندما يكون :other هو :value.',
    'different' => 'الحقل :attribute و :other يجب أن يكونا مختلفين.',
    'digits' => 'الحقل :attribute يجب أن يحتوي على :digits أرقام.',
    'digits_between' => 'الحقل :attribute يجب أن يكون بين :min و :max رقم.',
    'dimensions' => 'الحقل :attribute يحتوي على أبعاد صورة غير صالحة.',
    'distinct' => 'الحقل :attribute يحتوي على قيمة مكررة.',
    'doesnt_end_with' => 'الحقل :attribute يجب ألا ينتهي بأحد القيم التالية: :values.',
    'doesnt_start_with' => 'الحقل :attribute يجب ألا يبدأ بأحد القيم التالية: :values.',
    'email' => 'الحقل :attribute يجب أن يكون بريداً إلكترونياً صالحاً.',
    'ends_with' => 'الحقل :attribute يجب أن ينتهي بأحد القيم التالية: :values.',
    'enum' => 'الحقل :attribute المحدد غير صالح.',
    'exists' => 'الحقل :attribute المحدد غير صالح.',
    'extensions' => 'الحقل :attribute يجب أن يكون ملفاً من النوع: :values.',
    'file' => 'الحقل :attribute يجب أن يكون ملفاً.',
    'filled' => 'الحقل :attribute يجب أن يحتوي على قيمة.',
    'gt' => [
        'array' => 'الحقل :attribute يجب أن يحتوي على أكثر من :value عنصر.',
        'file' => 'الحقل :attribute يجب أن يكون أكبر من :value كيلوبايت.',
        'numeric' => 'الحقل :attribute يجب أن يكون أكبر من :value.',
        'string' => 'الحقل :attribute يجب أن يكون أكبر من :value حرفاً.',
    ],
    'gte' => [
        'array' => 'الحقل :attribute يجب أن يحتوي على :value عناصر أو أكثر.',
        'file' => 'الحقل :attribute يجب أن يكون أكبر من أو يساوي :value كيلوبايت.',
        'numeric' => 'الحقل :attribute يجب أن يكون أكبر من أو يساوي :value.',
        'string' => 'الحقل :attribute يجب أن يكون أكبر من أو يساوي :value حرفاً.',
    ],
    'hex_color' => 'الحقل :attribute يجب أن يكون لوناً سداسياً صحيحاً.',
    'image' => 'الحقل :attribute يجب أن يكون صورة.',
    'in' => 'الحقل :attribute المحدد غير صالح.',
    'in_array' => 'الحقل :attribute يجب أن يكون موجوداً في :other.',
    'integer' => 'الحقل :attribute يجب أن يكون عدداً صحيحاً.',
    'ip' => 'الحقل :attribute يجب أن يكون عنوان IP صحيحاً.',
    'ipv4' => 'الحقل :attribute يجب أن يكون عنوان IPv4 صحيحاً.',
    'ipv6' => 'الحقل :attribute يجب أن يكون عنوان IPv6 صحيحاً.',
    'json' => 'الحقل :attribute يجب أن يكون نص JSON صحيحاً.',
    'lowercase' => 'الحقل :attribute يجب أن يكون أحرفاً صغيرة.',
    'lt' => [
        'array' => 'الحقل :attribute يجب أن يحتوي على أقل من :value عنصر.',
        'file' => 'الحقل :attribute يجب أن يكون أقل من :value كيلوبايت.',
        'numeric' => 'الحقل :attribute يجب أن يكون أقل من :value.',
        'string' => 'الحقل :attribute يجب أن يكون أقل من :value حرفاً.',
    ],
    'lte' => [
        'array' => 'الحقل :attribute يجب ألا يحتوي على أكثر من :value عنصر.',
        'file' => 'الحقل :attribute يجب أن يكون أقل من أو يساوي :value كيلوبايت.',
        'numeric' => 'الحقل :attribute يجب أن يكون أقل من أو يساوي :value.',
        'string' => 'الحقل :attribute يجب أن يكون أقل من أو يساوي :value حرفاً.',
    ],
    'mac_address' => 'الحقل :attribute يجب أن يكون عنوان MAC صحيحاً.',
    'max' => [
        'array' => 'الحقل :attribute يجب ألا يحتوي على أكثر من :max عناصر.',
        'file' => 'الحقل :attribute يجب ألا يكون أكبر من :max كيلوبايت.',
        'numeric' => 'الحقل :attribute يجب ألا يكون أكبر من :max.',
        'string' => 'الحقل :attribute يجب ألا يكون أكبر من :max حرفاً.',
    ],
    'max_digits' => 'الحقل :attribute يجب ألا يحتوي على أكثر من :max أرقام.',
    'mimes' => 'الحقل :attribute يجب أن يكون ملفاً من النوع: :values.',
    'mimetypes' => 'الحقل :attribute يجب أن يكون ملفاً من النوع: :values.',
    'min' => [
        'array' => 'الحقل :attribute يجب أن يحتوي على الأقل :min عناصر.',
        'file' => 'الحقل :attribute يجب أن يكون على الأقل :min كيلوبايت.',
        'numeric' => 'الحقل :attribute يجب أن يكون على الأقل :min.',
        'string' => 'الحقل :attribute يجب أن يكون على الأقل :min حرفاً.',
    ],
    'min_digits' => 'الحقل :attribute يجب أن يحتوي على الأقل :min أرقام.',
    'multiple_of' => 'الحقل :attribute يجب أن يكون من مضاعفات :value.',
    'not_in' => 'الحقل :attribute المحدد غير صالح.',
    'not_regex' => 'صيغة الحقل :attribute غير صالحة.',
    'numeric' => 'الحقل :attribute يجب أن يكون رقماً.',
    'password' => [
        'letters' => 'الحقل :attribute يجب أن يحتوي على حرف واحد على الأقل.',
        'mixed' => 'الحقل :attribute يجب أن يحتوي على حرف كبير وصغير على الأقل.',
        'numbers' => 'الحقل :attribute يجب أن يحتوي على رقم واحد على الأقل.',
        'symbols' => 'الحقل :attribute يجب أن يحتوي على رمز واحد على الأقل.',
        'uncompromised' => 'الحقل :attribute ظهر في تسريب بيانات. يرجى اختيار قيمة مختلفة.',
    ],
    'present' => 'الحقل :attribute يجب أن يكون موجوداً.',
    'prohibited' => 'الحقل :attribute محظور.',
    'regex' => 'صيغة الحقل :attribute غير صالحة.',
    'required' => 'الحقل :attribute مطلوب.',
    'same' => 'الحقل :attribute و :other يجب أن يتطابقا.',
    'size' => [
        'array' => 'الحقل :attribute يجب أن يحتوي على :size عناصر.',
        'file' => 'الحقل :attribute يجب أن يكون :size كيلوبايت.',
        'numeric' => 'الحقل :attribute يجب أن يكون :size.',
        'string' => 'الحقل :attribute يجب أن يكون :size حرفاً.',
    ],
    'string' => 'الحقل :attribute يجب أن يكون نصاً.',
    'timezone' => 'الحقل :attribute يجب أن يكون منطقة زمنية صالحة.',
    'unique' => 'الحقل :attribute مأخوذ مسبقاً.',
    'uploaded' => 'فشل في تحميل الحقل :attribute.',
    'url' => 'الحقل :attribute يجب أن يكون رابطاً صحيحاً.',
    'phone' => 'رقم الهاتف الذي أدخلته غير صالح. يرجى إدخال رقم صحيح.',


    /*
    |--------------------------------------------------------------------------
    | رسائل التحقق المخصصة
    |--------------------------------------------------------------------------
    |
    | يمكنك تحديد رسائل مخصصة لمتطلبات معينة لكل حقل هنا.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'رسالة مخصصة.',
        ],
    ],

    'translatable' => [
        'required' => 'حقل :attribute مطلوب.',
        'string' => 'حقل :attribute يجب أن يكون نصاً.',
        'array' => 'حقل :attribute يجب أن يكون مصفوفة.',
        'unique' => 'حقل :attribute مأخوذ مسبقاً.',
        'max' => [
            'string' => 'حقل :attribute يجب ألا يكون أكبر من :max حرفاً.',
        ],
        'min' => [
            'string' => 'حقل :attribute يجب أن يكون على الأقل :min حرفاً.',
        ],
        'required_locale' => 'حقل :attribute مطلوب باللغة :locale.',
    ],
    /*
    |--------------------------------------------------------------------------
    | أسماء الحقول
    |--------------------------------------------------------------------------
    |
    | يمكنك تغيير أسماء الحقول الافتراضية هنا لتكون أكثر وضوحاً للمستخدمين.
    |
    */

    'attributes' => include 'attributes.php',

];
