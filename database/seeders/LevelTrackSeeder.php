<?php

namespace Database\Seeders;

use App\Models\Learning\Level;
use App\Models\Learning\LevelTrack;
use Illuminate\Database\Seeder;

class LevelTrackSeeder extends Seeder
{
    public function run(): void
    {
        $levels = Level::query()->get();

        foreach ($levels as $level) {
            $items = [];

            $lessons = $level->lessons()->orderBy('order_index')->get();
            foreach ($lessons as $lesson) {
                $items[] = [
                    'trackable_id' => $lesson->id,
                    'trackable_type' => \App\Models\Learning\Lesson::class,
                ];
            }

            $scenarios = $level->scenarios()->orderBy('order_index')->get();
            foreach ($scenarios as $scenario) {
                $items[] = [
                    'trackable_id' => $scenario->id,
                    'trackable_type' => \App\Models\Scenarios\Scenario::class,
                ];
            }

            $order = 1;
            foreach ($items as $item) {
                LevelTrack::withTrashed()->updateOrCreate(
                    [
                        'level_id' => $level->id,
                        'trackable_id' => $item['trackable_id'],
                        'trackable_type' => $item['trackable_type'],
                    ],
                    [
                        'order_index' => $order++,
                        'deleted_at' => null,
                    ]
                );
            }
        }
    }
}

