<?php

namespace App\Http\Services\Content;

use App\Models\Content\Podcast;
use App\Models\Progress\UserPodcastFavorite;
use App\Models\Progress\UserPodcastProgress;
use App\Models\Users\User;
use App\Services\MediaUrlService;
use App\Services\MessageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PodcastService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function index(array $filters, ?User $user): LengthAwarePaginator
    {
        $query = Podcast::query()->published();

        if (! empty($filters['course_id'])) {
            $query->where('course_id', (int) $filters['course_id']);
        }

        if (array_key_exists('is_free', $filters) && $filters['is_free'] !== null && $filters['is_free'] !== '') {
            $query->where('is_free', filter_var($filters['is_free'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $s = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', $s)
                    ->orWhere('description', 'like', $s);
            });
        }

        $status = $filters['status'] ?? 'all';

        if (! $user && in_array($status, ['continue_listening', 'favorites'], true)) {
            $status = 'all';
        }

        if ($user && $status === 'continue_listening') {
            $query->whereHas('userPodcastProgress', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('is_completed', false)->whereNull('deleted_at');
            });
        } elseif ($user && $status === 'favorites') {
            $query->whereHas('userPodcastFavorites', function ($q) use ($user) {
                $q->where('user_id', $user->id)->whereNull('deleted_at');
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        $query->orderBy('order_index')->orderByDesc('published_at')->orderByDesc('id');

        if ($user) {
            $uid = $user->id;
            $query->with([
                'userPodcastProgress'  => fn ($q) => $q->where('user_id', $uid),
                'userPodcastFavorites' => fn ($q) => $q->where('user_id', $uid)->whereNull('deleted_at'),
            ]);
        }

        return $query->paginate($perPage);
    }

    public function show(int $id, ?User $user): Podcast
    {
        $podcast = Podcast::query()->published()->where('id', $id)->first();
        if (! $podcast) {
            MessageService::abort(404, 'messages.podcast.not_found');
        }

        if ($user) {
            $uid = $user->id;
            $podcast->load([
                'userPodcastProgress'  => fn ($q) => $q->where('user_id', $uid),
                'userPodcastFavorites' => fn ($q) => $q->where('user_id', $uid)->whereNull('deleted_at'),
            ]);
        }

        return $podcast;
    }

    /**
     * @param  array{position_seconds: int, duration_seconds?: int|null}  $data
     */
    public function updateProgress(Podcast $podcast, User $user, array $data): UserPodcastProgress
    {
        $progress = UserPodcastProgress::withTrashed()->firstOrNew([
            'user_id'    => $user->id,
            'podcast_id' => $podcast->id,
        ]);

        if ($progress->trashed()) {
            $progress->restore();
        }

        $position         = max(0, (int) $data['position_seconds']);
        $reportedDuration = (array_key_exists('duration_seconds', $data) && $data['duration_seconds'] !== null)
            ? (int) $data['duration_seconds']
            : null;
        $podcastDuration  = (int) ($podcast->duration_seconds ?? 0);

        $effectiveDuration = ($reportedDuration !== null && $reportedDuration > 0)
            ? $reportedDuration
            : (($podcastDuration > 0) ? $podcastDuration : null);

        $pct       = (float) ($progress->progress_percentage ?? 0);
        $completed = false;

        if ($effectiveDuration !== null && $effectiveDuration > 0) {
            $position  = min($position, $effectiveDuration);
            $pct       = min(100.0, max(0.0, round(($position / $effectiveDuration) * 100, 2)));
            $nearEnd   = ($effectiveDuration - $position) <= 30;
            $completed = $pct >= 90.0 || $nearEnd;
        }

        $hasKnownDuration = $effectiveDuration !== null && $effectiveDuration > 0;

        $progress->position_seconds    = $position;
        $progress->duration_seconds    = $effectiveDuration ?? $reportedDuration ?? $progress->duration_seconds;
        $progress->progress_percentage = $hasKnownDuration ? $pct : $progress->progress_percentage;
        $progress->is_completed        = $hasKnownDuration ? $completed : false;
        $progress->last_played_at      = now();
        $progress->completed_at        = ($hasKnownDuration && $completed)
            ? ($progress->completed_at ?? now())
            : ($hasKnownDuration ? $progress->completed_at : null);

        $progress->save();

        return $progress->fresh();
    }

    public function toggleFavorite(Podcast $podcast, User $user, bool $add): void
    {
        $favorite = UserPodcastFavorite::withTrashed()->firstOrNew([
            'user_id'    => $user->id,
            'podcast_id' => $podcast->id,
        ]);

        if ($add) {
            if ($favorite->exists && $favorite->trashed()) {
                $favorite->restore();
            } elseif (! $favorite->exists) {
                $favorite->save();
            }
        } else {
            if ($favorite->exists && ! $favorite->trashed()) {
                $favorite->delete();
            }
        }
    }

    /**
     * @return array{is_locked: bool, lock_reason: string|null}
     */
    public function isLocked(Podcast $podcast, ?User $user): array
    {
        $open = $podcast->is_free || ($user && $user->hasActiveSubscription());
        if ($open) {
            return ['is_locked' => false, 'lock_reason' => null];
        }

        return [
            'is_locked'   => true,
            'lock_reason' => __('messages.podcast.locked_subscription'),
        ];
    }

    public function resolveAudioUrl(Podcast $podcast, ?User $user): ?string
    {
        if ($this->isLocked($podcast, $user)['is_locked']) {
            return null;
        }

        if ($podcast->audio_url) {
            return MediaUrlService::toUrl($podcast->audio_url);
        }

        if ($podcast->audio_path) {
            return MediaUrlService::toUrl($podcast->audio_path);
        }

        return null;
    }

    public function resolveDownloadUrl(Podcast $podcast, ?User $user): ?string
    {
        if (! $podcast->allow_download) {
            return null;
        }

        return $this->resolveAudioUrl($podcast, $user);
    }
}
