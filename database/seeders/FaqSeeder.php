<?php

namespace Database\Seeders;

use App\Models\Content\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            ['question' => 'كيف أسجل حساب جديد؟', 'answer' => 'من صفحة التسجيل أدخل البيانات المطلوبة ثم فعّل حسابك.'],
            ['question' => 'هل يمكنني استخدام التطبيق بدون تسجيل دخول؟', 'answer' => 'نعم لبعض المحتوى المنشور، بينما الميزات الخاصة تتطلب تسجيل دخول.'],
            ['question' => 'كيف أرى الدروس المتاحة؟', 'answer' => 'من صفحة الدورات اختر دورة ثم مستوى ثم الدروس المتاحة.'],
            ['question' => 'هل توجد دورات مجانية؟', 'answer' => 'نعم، بعض الدورات أو المستويات تكون مجانية حسب الإعدادات.'],
            ['question' => 'كيف أحصل على شهادة؟', 'answer' => 'أكمل المستوى أو الدورة المطلوبة حسب سياسة الشهادة.'],
            ['question' => 'كيف أغير كلمة المرور؟', 'answer' => 'من الملف الشخصي يمكنك تحديث كلمة المرور بعد إدخال الحالية.'],
            ['question' => 'كيف أتواصل مع الدعم؟', 'answer' => 'يمكنك التواصل عبر صفحة تواصل معنا أو البريد الموجود في الإعدادات.'],
            ['question' => 'هل البيانات آمنة؟', 'answer' => 'نستخدم أفضل الممارسات لحماية البيانات وتطبيق الصلاحيات حسب السياق.'],
        ];

        foreach ($faqs as $faq) {
            Faq::withTrashed()->updateOrCreate(
                ['question' => $faq['question']],
                [
                    'answer' => $faq['answer'],
                    'deleted_at' => null,
                ]
            );
        }
    }
}

