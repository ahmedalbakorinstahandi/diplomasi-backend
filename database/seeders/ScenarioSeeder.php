<?php

namespace Database\Seeders;

use App\Models\Learning\Level;
use App\Models\Scenarios\Scenario;
use App\Services\OrderHelper;
use Illuminate\Database\Seeder;

class ScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $levels = Level::query()->get();

        foreach ($levels as $level) {
            for ($i = 1; $i <= 5; $i++) {
                $scenario = Scenario::withTrashed()->updateOrCreate(
                    [
                        'level_id' => $level->id,
                        'title' => "سيناريو {$i}",
                    ],
                    [
                        'description' => "وصف تجريبي للسيناريو {$i} ضمن {$level->title}.",
                        'is_published' => (bool) $level->is_published && $i !== 5,
                        'is_free' => $i === 1 ? (bool) $level->is_free : false,
                        'start_question_id' => null,
                        'deleted_at' => null,
                    ]
                );

                if ($scenario->wasRecentlyCreated || $scenario->order_index === null) {
                    OrderHelper::assign($scenario, 'order_index');
                }
            }
        }
    }
}
