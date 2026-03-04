<?php

namespace Database\Seeders;

use App\Models\System\ReengagementReminder;
use Illuminate\Database\Seeder;

class ReengagementReminderSeeder extends Seeder
{
    /**
     * القواعد الأولية لتذكيرات العودة (المتفق عليها).
     * يُدخل السجلات فقط عندما يكون الجدول فارغاً حتى لا يمسّ تعديلات الأدمن.
     */
    public function run(): void
    {
        if (ReengagementReminder::query()->exists()) {
            return;
        }

        $defaults = [
            [
                'amount' => 1,
                'unit' => 'day',
                'title' => 'مكانك لسا محجوز عندنا',
                'body' => 'رجعتك اليوم بتعمل فرق كبير. افتح دبلوماسي وخذ خطوة صغيرة ترفع مستواك بسرعة.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'amount' => 3,
                'unit' => 'day',
                'title' => 'اشتقنالك في دبلوماسي',
                'body' => 'ثلاث أيام غياب كفاية. ارجع الآن وكمل رحلتك من آخر نقطة وصلت لها.',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'amount' => 7,
                'unit' => 'day',
                'title' => 'أسبوع بدونك كثير',
                'body' => 'مهاراتك تستحق ترجع تتحرك. افتح التطبيق الآن وخلينا نكمل الإنجاز سوا.',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'amount' => 14,
                'unit' => 'day',
                'title' => 'خلّي العودة تكون قوية',
                'body' => 'مرّ وقت، لكن البداية دائمًا بإيدك. دقيقة واحدة الآن كفيلة تعيدك للمسار.',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'amount' => 30,
                'unit' => 'day',
                'title' => 'رجعتك اليوم بداية جديدة',
                'body' => 'شهر غياب وما زالت فرصتك كبيرة. ارجع اليوم وخذ دفعة جديدة نحو هدفك.',
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($defaults as $item) {
            ReengagementReminder::query()->create($item);
        }
    }
}
