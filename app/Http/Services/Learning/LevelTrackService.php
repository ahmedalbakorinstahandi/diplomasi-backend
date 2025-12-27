<?php

namespace App\Http\Services\Learning;

use App\Http\Permissions\Learning\LevelTrackPermission;
use App\Models\Learning\LevelTrack;
use App\Models\Learning\Lesson;
use App\Models\Scenarios\Scenario;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;

class LevelTrackService
{
    public function index($filters = [])
    {
        $query = LevelTrack::query()->with([
            'level',
            'trackable',
        ]);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'order_index';
        $filters['sort_order'] = $filters['sort_order'] ?? 'asc';

        $searchFields = [];
        $numericFields = ['order_index'];
        $dateFields = ['created_at'];
        $exactMatchFields = ['level_id', 'trackable_type'];
        $inFields = [];

        $query = LevelTrackPermission::filterIndex($query);

        $query = FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );

        return $query;
    }

    public function show(int $id)
    {
        $levelTrack = LevelTrack::where('id', $id)->first();
        if (!$levelTrack) {
            MessageService::abort(404, 'messages.level_track.not_found');
        }

        $levelTrack->load([
            'level',
            'trackable',
        ]);

        return $levelTrack;
    }

    public function create($data)
    {
        // Validate trackable exists
        $trackableType = $data['trackable_type'];
        $trackableId = $data['trackable_id'];

        if ($trackableType === Lesson::class) {
            $trackable = Lesson::find($trackableId);
            if (!$trackable) {
                MessageService::abort(404, 'messages.lesson.not_found');
            }
            // Ensure level_id matches
            if ($trackable->level_id != $data['level_id']) {
                MessageService::abort(400, 'messages.level_track.level_mismatch');
            }
        } elseif ($trackableType === Scenario::class) {
            $trackable = Scenario::find($trackableId);
            if (!$trackable) {
                MessageService::abort(404, 'messages.scenario.not_found');
            }
            // Ensure level_id matches
            if ($trackable->level_id != $data['level_id']) {
                MessageService::abort(400, 'messages.level_track.level_mismatch');
            }
        } else {
            MessageService::abort(400, 'messages.level_track.invalid_trackable_type');
        }

        // Check if track already exists
        $existing = LevelTrack::where('level_id', $data['level_id'])
            ->where('trackable_id', $trackableId)
            ->where('trackable_type', $trackableType)
            ->withTrashed()
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $levelTrack = $existing;
            } else {
                MessageService::abort(400, 'messages.level_track.already_exists');
            }
        } else {
            $levelTrack = LevelTrack::create($data);
        }

        OrderHelper::assign($levelTrack, 'order_index');

        $levelTrack = $this->show($levelTrack->id);

        return $levelTrack;
    }

    public function update($data, $levelTrack)
    {
        // If level_id is being changed, validate trackable's level_id matches
        if (isset($data['level_id']) && $data['level_id'] != $levelTrack->level_id) {
            $trackable = $levelTrack->trackable;
            if ($trackable && $trackable->level_id != $data['level_id']) {
                MessageService::abort(400, 'messages.level_track.level_mismatch');
            }
        }

        $levelTrack->update($data);

        $levelTrack = $this->show($levelTrack->id);

        return $levelTrack;
    }

    public function delete($levelTrack)
    {
        $levelTrack->delete();
    }

    public function reorder($levelTrack, $validatedData)
    {
        OrderHelper::reorder($levelTrack, $validatedData['new_order_index'], 'order_index');

        return $this->show($levelTrack->id);
    }

    /**
     * Sync level tracks for a level - ensures all lessons and scenarios are tracked
     */
    public function syncForLevel(int $levelId)
    {
        $level = \App\Models\Learning\Level::find($levelId);
        if (!$level) {
            MessageService::abort(404, 'messages.level.not_found');
        }

        $items = [];

        // Get all lessons for this level
        $lessons = $level->lessons()->orderBy('order_index')->get();
        foreach ($lessons as $lesson) {
            $items[] = [
                'trackable_id' => $lesson->id,
                'trackable_type' => Lesson::class,
            ];
        }

        // Get all scenarios for this level
        $scenarios = $level->scenarios()->orderBy('order_index')->get();
        foreach ($scenarios as $scenario) {
            $items[] = [
                'trackable_id' => $scenario->id,
                'trackable_type' => Scenario::class,
            ];
        }

        // Create or update level tracks
        foreach ($items as $item) {
            $track = LevelTrack::withTrashed()->updateOrCreate(
                [
                    'level_id' => $levelId,
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

        // Soft delete tracks that are no longer in the level
        $currentTrackableIds = collect($items)->pluck('trackable_id')->toArray();
        LevelTrack::where('level_id', $levelId)
            ->whereNotIn('trackable_id', $currentTrackableIds)
            ->delete();

        return $this->index(['level_id' => $levelId]);
    }
}

