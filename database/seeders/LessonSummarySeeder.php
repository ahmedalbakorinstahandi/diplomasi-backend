<?php

namespace Database\Seeders;

use App\Models\Learning\Lesson;
use App\Models\Learning\LessonSummary;
use Illuminate\Database\Seeder;

class LessonSummarySeeder extends Seeder
{
    public function run(): void
    {
        $lessons = Lesson::query()->get();

        foreach ($lessons as $lesson) {
            LessonSummary::withTrashed()->updateOrCreate(
                ['lesson_id' => $lesson->id],
                [
                    'content' => "ملخص الدرس: {$lesson->title}. نقاط سريعة + تذكير بالمفاهيم الأساسية.",
                    'attached_path' => 'summaries/lesson_' . $lesson->id . '.pdf',
                    'deleted_at' => null,
                ]
            );
        }
    }
}

