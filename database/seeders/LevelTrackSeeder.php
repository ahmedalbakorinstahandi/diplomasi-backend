<?php

namespace Database\Seeders;

use App\Models\Learning\Level;
use App\Models\Learning\LevelTrack;
use App\Services\OrderHelper;
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

            foreach ($items as $item) {
                $track = LevelTrack::withTrashed()->updateOrCreate(
                    [
                        'level_id' => $level->id,
                        'trackable_id' => $item['trackable_id'],
                        'trackable_type' => $item['trackable_type'],
                    ],
                    [
                        'deleted_at' => null,
                    ]
                );

                if ($track->wasRecentlyCreated || $track->order_index === null) {
                    OrderHelper::assign($track, 'order_index');
                }
            }
        }
    }
}

