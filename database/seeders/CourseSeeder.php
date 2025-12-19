<?php

namespace Database\Seeders;

use App\Models\Learning\Course;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $courses = [
            [
                'title' => 'مبادئ الدبلوماسية الحديثة',
                'description' => 'مدخل عملي لفهم أدوات الدبلوماسية، وبناء العلاقات، وإدارة الأزمات.',
                'image_url' => 'https://picsum.photos/id/1011/800/400',
                'is_published' => true,
                'is_free' => true,
            ],
            [
                'title' => 'فن التفاوض والاقناع',
                'description' => 'استراتيجيات تفاوض فعّالة، وكيفية إعداد خطة تفاوض وتحقيق أفضل نتيجة.',
                'image_url' => 'https://picsum.photos/id/1025/800/400',
                'is_published' => true,
                'is_free' => false,
            ],
            [
                'title' => 'إدارة النزاعات وحل الخلافات',
                'description' => 'نماذج تحليل النزاع، وطرق التدخل، وتقنيات بناء التوافق.',
                'image_url' => 'https://picsum.photos/id/1031/800/400',
                'is_published' => true,
                'is_free' => false,
            ],
            [
                'title' => 'بروتوكول ومراسم رسمية',
                'description' => 'أساسيات البروتوكول، ترتيب الأولويات، وإدارة الفعاليات الرسمية باحتراف.',
                'image_url' => 'https://picsum.photos/id/1040/800/400',
                'is_published' => true,
                'is_free' => true,
            ],
            [
                'title' => 'كتابة المذكرات والتقارير',
                'description' => 'صياغة مذكرات موجزة وتقارير تحليلية وملخصات تنفيذية بأسلوب واضح.',
                'image_url' => 'https://picsum.photos/id/1050/800/400',
                'is_published' => true,
                'is_free' => false,
            ],
            [
                'title' => 'تحليل السياسات العامة',
                'description' => 'أدوات تحليل السياسات، أصحاب المصلحة، وتقييم الخيارات والآثار.',
                'image_url' => 'https://picsum.photos/id/1062/800/400',
                'is_published' => true,
                'is_free' => false,
            ],
            [
                'title' => 'تواصل استراتيجي وإعلام',
                'description' => 'إدارة الرسائل، التعامل مع الإعلام، وبناء سردية مؤسسية متماسكة.',
                'image_url' => 'https://picsum.photos/id/1067/800/400',
                'is_published' => true,
                'is_free' => true,
            ],
            [
                'title' => 'قيادة الفرق والعمل المؤسسي',
                'description' => 'بناء فرق عالية الأداء، تفويض فعّال، وإدارة توقعات أصحاب المصلحة.',
                'image_url' => 'https://picsum.photos/id/1074/800/400',
                'is_published' => true,
                'is_free' => false,
            ],
            [
                'title' => 'إدارة الوقت والإنتاجية',
                'description' => 'أدوات عملية لإدارة الوقت، ترتيب الأولويات، وتقليل التشتت.',
                'image_url' => 'https://picsum.photos/id/1084/800/400',
                'is_published' => true,
                'is_free' => true,
            ],
            [
                'title' => 'دبلوماسية رقمية وأمن معلومات',
                'description' => 'أساسيات الأمن الرقمي، السلوك الآمن، وإدارة السمعة الرقمية.',
                'image_url' => 'https://picsum.photos/id/1080/800/400',
                'is_published' => false,
                'is_free' => false,
            ],
            [
                'title' => 'اتصال بين الثقافات',
                'description' => 'فهم الفروقات الثقافية وتجنب سوء الفهم وبناء تواصل محترم وفعّال.',
                'image_url' => 'https://picsum.photos/id/1081/800/400',
                'is_published' => false,
                'is_free' => true,
            ],
            [
                'title' => 'التخطيط الاستراتيجي',
                'description' => 'صياغة الأهداف، مؤشرات الأداء، وخارطة طريق قابلة للتنفيذ.',
                'image_url' => 'https://picsum.photos/id/1082/800/400',
                'is_published' => true,
                'is_free' => false,
            ],
        ];

        foreach ($courses as $course) {
            Course::withTrashed()->updateOrCreate(
                ['title' => $course['title']],
                [
                    'description' => $course['description'],
                    'image_url' => $course['image_url'],
                    'is_published' => $course['is_published'],
                    'is_free' => $course['is_free'],
                    'deleted_at' => null,
                ]
            );
        }
    }
}

