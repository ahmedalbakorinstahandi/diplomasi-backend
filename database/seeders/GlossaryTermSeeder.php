<?php

namespace Database\Seeders;

use App\Models\Learning\GlossaryTerm;
use Illuminate\Database\Seeder;

class GlossaryTermSeeder extends Seeder
{
    public function run(): void
    {
        $terms = [
            ['term' => 'الدبلوماسية', 'definition' => 'فن إدارة العلاقات بين الدول أو الجهات عبر الحوار والتفاوض.'],
            ['term' => 'التفاوض', 'definition' => 'عملية تواصل للوصول إلى اتفاق يحقق مصالح الأطراف قدر الإمكان.'],
            ['term' => 'الوساطة', 'definition' => 'تدخل طرف ثالث لمساعدة الأطراف على حل نزاع بشكل سلمي.'],
            ['term' => 'أصحاب المصلحة', 'definition' => 'الأفراد/الجهات المتأثرة أو المؤثرة في قرار أو مشروع.'],
            ['term' => 'إدارة المخاطر', 'definition' => 'تحديد المخاطر وتقييمها ووضع خطط للتخفيف من أثرها.'],
            ['term' => 'بروتوكول', 'definition' => 'مجموعة قواعد تنظيمية للسلوك والمراسم الرسمية.'],
            ['term' => 'التواصل الاستراتيجي', 'definition' => 'بناء الرسائل والتخطيط لها لتحقيق أهداف مؤسسية واضحة.'],
            ['term' => 'تحليل السياسة', 'definition' => 'تقييم الخيارات والآثار لتقديم توصيات لصنّاع القرار.'],
            ['term' => 'إدارة النزاع', 'definition' => 'أدوات ومقاربات لفهم النزاعات وتقليل التصعيد وتحقيق التسويات.'],
            ['term' => 'خطة عمل', 'definition' => 'تفصيل المهام والأدوار والجدول الزمني لتحقيق هدف محدد.'],
        ];

        foreach ($terms as $t) {
            GlossaryTerm::withTrashed()->updateOrCreate(
                ['term' => $t['term'], 'language' => 'ar'],
                [
                    'definition' => $t['definition'],
                    'deleted_at' => null,
                ]
            );
        }
    }
}

