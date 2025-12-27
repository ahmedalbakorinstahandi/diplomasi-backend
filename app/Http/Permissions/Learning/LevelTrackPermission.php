<?php

namespace App\Http\Permissions\Learning;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;
use Illuminate\Support\Facades\DB;

class LevelTrackPermission
{
    public static function filterIndex($query)
    {
        self::canView();

        // For app context, only show tracks for published levels and published trackables
        if (RequestContext::isApp()) {
            $query->whereHas('level', function ($q) {
                $q->where('is_published', true);
            })->where(function ($q) {
                // For lessons: check if lesson is published (check if trackable_type contains "lesson", case-insensitive)
                $q->where(function ($subQ) {
                    $subQ->whereRaw('LOWER(trackable_type) LIKE ?', ['%lesson%'])
                        ->whereExists(function ($existsQ) {
                            $existsQ->select(DB::raw(1))
                                ->from('lessons')
                                ->whereColumn('lessons.id', 'level_tracks.trackable_id')
                                ->where('lessons.is_published', true);
                        });
                })
                // For scenarios: check if scenario is published (check if trackable_type contains "scenario", case-insensitive)
                ->orWhere(function ($subQ) {
                    $subQ->whereRaw('LOWER(trackable_type) LIKE ?', ['%scenario%'])
                        ->whereExists(function ($existsQ) {
                            $existsQ->select(DB::raw(1))
                                ->from('scenarios')
                                ->whereColumn('scenarios.id', 'level_tracks.trackable_id')
                                ->where('scenarios.is_published', true);
                        });
                });
            });
        }

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('level_track.view');
        }
        // App context: allow viewing (public)
    }

    public static function canShow($levelTrack): void
    {
        self::canView();
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('level_track.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('level_track.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('level_track.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canReorder(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('level_track.reorder');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}

