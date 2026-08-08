<?php

namespace Database\Seeders;

use App\Models\AiNegotiator\AiNegotiatorRubricItem;
use Illuminate\Database\Seeder;

class AiNegotiatorRubricItemSeeder extends Seeder
{
    /**
     * Seed the 8 AI Negotiator rubric items (weights sum to 100).
     */
    public function run(): void
    {
        $items = [
            [
                'code' => 'goal_clarity',
                'title' => 'وضوح الهدف',
                'description' => 'مدى وضوح هدف المستخدم وقدرته على التعبير عنه دون غموض.',
                'weight' => 10,
                'order_index' => 1,
            ],
            [
                'code' => 'opening_strength',
                'title' => 'قوة البداية',
                'description' => 'جودة الافتتاحية وقدرتها على تثبيت الإطار التفاوضي من البداية.',
                'weight' => 10,
                'order_index' => 2,
            ],
            [
                'code' => 'questioning',
                'title' => 'طرح الأسئلة',
                'description' => 'استخدام أسئلة فعّالة لاستكشاف المصالح والمعلومات بدل الافتراض.',
                'weight' => 15,
                'order_index' => 3,
            ],
            [
                'code' => 'interest_understanding',
                'title' => 'فهم مصلحة الطرف الآخر',
                'description' => 'إظهار فهم لمصالح الطرف الآخر والعمل عليها لا الاكتفاء بالمواقف.',
                'weight' => 15,
                'order_index' => 4,
            ],
            [
                'code' => 'objection_handling',
                'title' => 'إدارة الاعتراضات',
                'description' => 'التعامل مع الاعتراضات بهدوء ومنطق دون تصعيد أو استسلام.',
                'weight' => 15,
                'order_index' => 5,
            ],
            [
                'code' => 'no_quick_concession',
                'title' => 'عدم التنازل السريع',
                'description' => 'تجنب التنازل المبكر دون مقابل والحفاظ على قيمة العرض.',
                'weight' => 15,
                'order_index' => 6,
            ],
            [
                'code' => 'calm_assertiveness',
                'title' => 'الحزم الهادئ',
                'description' => 'الجمع بين الحزم والهدوء دون عدوانية أو تردد مفرط.',
                'weight' => 10,
                'order_index' => 7,
            ],
            [
                'code' => 'relationship_building',
                'title' => 'بناء العلاقة',
                'description' => 'الحفاظ على العلاقة والاحترام أثناء السعي لتحقيق الهدف.',
                'weight' => 10,
                'order_index' => 8,
            ],
        ];

        foreach ($items as $item) {
            AiNegotiatorRubricItem::withTrashed()->updateOrCreate(
                ['code' => $item['code']],
                [
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'weight' => $item['weight'],
                    'order_index' => $item['order_index'],
                    'is_published' => true,
                    'deleted_at' => null,
                ]
            );
        }
    }
}
