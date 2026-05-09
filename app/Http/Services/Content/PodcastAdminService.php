<?php

namespace App\Http\Services\Content;

use App\Http\Permissions\Content\PodcastPermission;
use App\Models\Content\Podcast;
use App\Services\FileService;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\OrderHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PodcastAdminService
{
    // ── Listing ───────────────────────────────────────────────────────────────────

    public function index(array $filters = []): LengthAwarePaginator
    {
        $query = Podcast::query()->with(['course']);

        // Include soft-deleted when explicitly requested
        if (! empty($filters['with_trashed'])) {
            $query->withTrashed();
        } elseif (! empty($filters['only_trashed'])) {
            $query->onlyTrashed();
        }

        $filters['per_page']   = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'order_index';
        $filters['sort_order'] = $filters['sort_order'] ?? 'asc';

        $searchFields     = ['title', 'description', 'slug'];
        $numericFields    = ['duration_seconds', 'order_index'];
        $dateFields       = ['created_at', 'published_at'];
        $exactMatchFields = ['is_published', 'is_free', 'allow_download', 'requires_subscription', 'course_id'];
        $inFields         = [];

        $query = PodcastPermission::filterIndex($query);

        return FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );
    }

    // ── Single record ─────────────────────────────────────────────────────────────

    public function show(int $id, bool $withTrashed = false): Podcast
    {
        $query = $withTrashed ? Podcast::withTrashed() : Podcast::query();

        $podcast = $query->with(['course'])->where('id', $id)->first();
        if (! $podcast) {
            MessageService::abort(404, 'messages.podcast.not_found');
        }

        return $podcast;
    }

    // ── Create ─────────────────────────────────────────────────────────────────────

    public function create(array $data): Podcast
    {
        $data = $this->resolveSlug($data);
        $data = $this->resolveAudioFile($data, null);

        $podcast = Podcast::create($this->toFillable($data));

        OrderHelper::assign($podcast);

        return $this->show($podcast->id);
    }

    // ── Update ─────────────────────────────────────────────────────────────────────

    public function update(array $data, Podcast $podcast): Podcast
    {
        $wasPublished = (bool) $podcast->is_published;

        if (isset($data['slug']) && $data['slug'] === null) {
            $data['slug'] = null;
        }

        $data = $this->resolveAudioFile($data, $podcast);

        $podcast->update($this->toFillable($data));

        // Auto-set published_at when first published
        if (! $wasPublished && $podcast->is_published && $podcast->published_at === null) {
            $podcast->update(['published_at' => now()]);
        }

        return $this->show($podcast->id);
    }

    // ── Publish toggle ────────────────────────────────────────────────────────────

    public function togglePublish(Podcast $podcast): Podcast
    {
        $newState = ! $podcast->is_published;
        $update   = ['is_published' => $newState];

        if ($newState && $podcast->published_at === null) {
            $update['published_at'] = now();
        }

        $podcast->update($update);

        return $this->show($podcast->id);
    }

    // ── Delete ─────────────────────────────────────────────────────────────────────

    public function delete(Podcast $podcast): void
    {
        $podcast->delete();
    }

    // ── Restore ────────────────────────────────────────────────────────────────────

    public function restore(int $id): Podcast
    {
        $podcast = Podcast::withTrashed()->where('id', $id)->first();
        if (! $podcast) {
            MessageService::abort(404, 'messages.podcast.not_found');
        }

        if (! $podcast->trashed()) {
            MessageService::abort(422, 'messages.podcast.not_deleted');
        }

        $podcast->restore();

        return $this->show($podcast->id);
    }

    // ── Reorder ────────────────────────────────────────────────────────────────────

    public function reorder(Podcast $podcast, array $validated): void
    {
        OrderHelper::reorder($podcast, (int) $validated['new_order_index'], 'order_index');
    }

    // ── Statistics ─────────────────────────────────────────────────────────────────

    /**
     * Lightweight computed statistics for a single podcast.
     *
     * @return array{
     *   total_plays: int,
     *   completed_listens: int,
     *   favorites_count: int,
     *   progress_records: int
     * }
     */
    public function stats(Podcast $podcast): array
    {
        return [
            'total_plays'       => $podcast->userPodcastProgress()->count(),
            'completed_listens' => $podcast->userPodcastProgress()->where('is_completed', true)->count(),
            'favorites_count'   => $podcast->userPodcastFavorites()->count(),
            'progress_records'  => $podcast->userPodcastProgress()->count(),
        ];
    }

    /**
     * Aggregate statistics across all podcasts (dashboard overview).
     *
     * @return array{
     *   total_podcasts: int,
     *   published_podcasts: int,
     *   total_plays: int,
     *   total_completed: int,
     *   total_favorites: int
     * }
     */
    public function globalStats(): array
    {
        return [
            'total_podcasts'    => Podcast::count(),
            'published_podcasts'=> Podcast::where('is_published', true)->count(),
            'total_plays'       => \App\Models\Progress\UserPodcastProgress::count(),
            'total_completed'   => \App\Models\Progress\UserPodcastProgress::where('is_completed', true)->count(),
            'total_favorites'   => \App\Models\Progress\UserPodcastFavorite::count(),
        ];
    }

    // ── Audio file handling ────────────────────────────────────────────────────────

    /**
     * Resolve the audio source from one of two mutually exclusive inputs:
     *
     *   audio_path — relative path returned by general/upload-file
     *     → delete old stored file when the path changes
     *     → clear audio_url (stored file takes precedence)
     *     → attempt duration extraction from the stored file
     *
     *   audio_url  — external URL
     *     → flows through toFillable() as-is; no special handling needed
     *
     * If neither is present the data is returned unchanged.
     */
    private function resolveAudioFile(array $data, ?Podcast $existing): array
    {
        if (isset($data['audio_path']) && is_string($data['audio_path']) && $data['audio_path'] !== '') {
            // Delete old stored file only when the path actually changes
            if ($existing && $existing->audio_path && $existing->audio_path !== $data['audio_path']) {
                FileService::deleteFile($existing->audio_path);
            }

            $data['audio_url'] = null;

            // Attempt duration extraction from the already-stored file
            $fullPath = Storage::path($data['audio_path']);
            if (file_exists($fullPath)) {
                $duration = $this->extractDurationSeconds($fullPath);
                if ($duration !== null) {
                    $data['duration_seconds'] = $duration;
                }
            }
        }

        return $data;
    }

    /**
     * Attempt to extract duration (in whole seconds) from an audio file.
     * Returns null silently on failure — never blocks the upload.
     */
    private function extractDurationSeconds(string $filePath): ?int
    {
        try {
            if (class_exists('\getID3')) {
                // getID3 library is available — use it for accurate duration extraction
                /** @phpstan-ignore-next-line */
                $getId3 = new \getID3(); // @phpstan-ignore-line
                $info   = $getId3->analyze($filePath);
                if (isset($info['playtime_seconds'])) {
                    return (int) round($info['playtime_seconds']);
                }
            }

            // Fallback: use ffprobe if available on the server
            if ($this->commandExists('ffprobe')) {
                $output = shell_exec(
                    sprintf(
                        'ffprobe -v quiet -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
                        escapeshellarg($filePath)
                    )
                );
                $seconds = $output !== null ? (float) trim((string) $output) : 0;
                if ($seconds > 0) {
                    return (int) round($seconds);
                }
            }
        } catch (\Throwable) {
            // Silently fail — duration remains nullable
        }

        return null;
    }

    private function commandExists(string $command): bool
    {
        $result = shell_exec(sprintf('which %s 2>/dev/null', escapeshellarg($command)));
        return ! empty(trim((string) $result));
    }

    // ── Slug helper ───────────────────────────────────────────────────────────────

    private function resolveSlug(array $data): array
    {
        if (empty($data['slug']) && ! empty($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        return $data;
    }

    // ── Fillable filter ───────────────────────────────────────────────────────────

    /**
     * Strip non-fillable keys (like the raw uploaded file) before passing to Eloquent.
     */
    private function toFillable(array $data): array
    {
        $allowed = [
            'course_id', 'title', 'slug', 'description', 'cover_image',
            'audio_url', 'audio_path', 'duration_seconds',
            'is_published', 'is_free', 'requires_subscription', 'allow_download',
            'order_index', 'published_at',
        ];

        return array_intersect_key($data, array_flip($allowed));
    }
}
