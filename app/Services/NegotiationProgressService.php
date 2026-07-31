<?php

namespace App\Services;

use App\Events\UserNegotiationLevelCompleted;
use App\Models\Negotiation\NegotiationLevel;
use App\Models\Negotiation\NegotiationSituation;
use App\Models\Negotiation\UserNegotiationLevelProgress;
use App\Models\Negotiation\UserNegotiationSituationAttempt;
use App\Models\Negotiation\UserNegotiationSituationProgress;
use App\Models\Users\User;
use Illuminate\Support\Facades\Event;

class NegotiationProgressService
{
    public const ACCESS_REASON_PROGRESS = 'progress';
    public const ACCESS_REASON_SUBSCRIPTION = 'subscription';

    /**
     * Get situation access status for a user.
     * Runtime statuses: open | locked | locked_by_subscription | in_progress | completed.
     * DB track_status remains narrow (locked|open|completed); this method injects the extras.
     *
     * Mirrors TrackProgressService::getTrackStatus (L35–76) with governance overrides from
     * loadProgressDataForTracks (L892–922): completed / in_progress stay accessible.
     */
    public function getSituationAccessStatus(NegotiationSituation $situation, int $userId): string
    {
        $blockingReason = $this->getSituationBlockingReason($situation, $userId);

        // Governance: completed stays completed/accessible even if prerequisites change
        // (mirrors loadProgressDataForTracks L892–895).
        if ($this->isSituationCompleted($situation, $userId)) {
            return 'completed';
        }

        // Governance: in_progress stays accessible (mirrors loadProgressDataForTracks L913–922).
        if ($this->hasInProgressSituation($situation, $userId)) {
            return 'in_progress';
        }

        if ($blockingReason !== null) {
            return $blockingReason === self::ACCESS_REASON_SUBSCRIPTION
                ? 'locked_by_subscription'
                : 'locked';
        }

        $progress = UserNegotiationSituationProgress::where('user_id', $userId)
            ->where('negotiation_situation_id', $situation->id)
            ->first();

        if ($progress && $progress->track_status) {
            return $progress->track_status;
        }

        return 'open';
    }

    /**
     * Check if user can access a situation (blocking reason is null),
     * with governance: completed / in_progress always accessible.
     */
    public function canAccessSituation(NegotiationSituation $situation, int $userId): bool
    {
        if ($this->isSituationCompleted($situation, $userId)) {
            return true;
        }

        if ($this->hasInProgressSituation($situation, $userId)) {
            return true;
        }

        return $this->getSituationBlockingReason($situation, $userId) === null;
    }

    /**
     * Mirror TrackProgressService::getTrackBlockingReason (L93–127).
     *
     * @return string|null 'subscription' | 'progress' | null
     */
    public function getSituationBlockingReason(NegotiationSituation $situation, int $userId): ?string
    {
        if ($this->isSituationLockedBySubscription($situation, $userId)) {
            return self::ACCESS_REASON_SUBSCRIPTION;
        }

        $siblings = NegotiationSituation::where('negotiation_level_id', $situation->negotiation_level_id)
            ->orderBy('order_index', 'asc')
            ->get();

        foreach ($siblings as $sibling) {
            if ($sibling->order_index >= $situation->order_index) {
                break;
            }

            if (!$this->isSituationPublished($sibling)) {
                continue;
            }

            // Subscription-locked situations are skipped so they do not break the chain.
            if ($this->isSituationLockedBySubscription($sibling, $userId)) {
                continue;
            }

            if (!$this->isSituationCompleted($sibling, $userId)) {
                return self::ACCESS_REASON_PROGRESS;
            }
        }

        return null;
    }

    /**
     * Mirror TrackProgressService::isLockedBySubscription (L129–153) for situations.
     */
    public function isSituationLockedBySubscription(NegotiationSituation $situation, int $userId): bool
    {
        if (!$this->isSituationPublished($situation)) {
            return false;
        }

        if ((bool) ($situation->is_free ?? false)) {
            return false;
        }

        $user = User::find($userId);
        if (!$user) {
            return true;
        }

        return !$user->hasActiveSubscription();
    }

    public function isSituationPublished(NegotiationSituation $situation): bool
    {
        return (bool) ($situation->is_published ?? false);
    }

    public function isLevelPublished(NegotiationLevel $level): bool
    {
        return (bool) ($level->is_published ?? false);
    }

    /**
     * Mirror TrackProgressService::isLessonCompleted OR-of-signals (L221–257).
     */
    public function isSituationCompleted(NegotiationSituation $situation, int $userId): bool
    {
        $progress = UserNegotiationSituationProgress::where('user_id', $userId)
            ->where('negotiation_situation_id', $situation->id)
            ->first();

        if ($progress) {
            if (isset($progress->is_completed) && $progress->is_completed === true) {
                return true;
            }

            if (isset($progress->status) && $progress->status === 'completed') {
                return true;
            }

            if (isset($progress->track_status) && $progress->track_status === 'completed') {
                return true;
            }
        }

        $firstFinishedAttempt = UserNegotiationSituationAttempt::where('user_id', $userId)
            ->where('negotiation_situation_id', $situation->id)
            ->where('status', 'finished')
            ->orderBy('finished_at', 'asc')
            ->first();

        return $firstFinishedAttempt !== null;
    }

    /**
     * Mirror TrackProgressService::updateLessonProgress (L420–465) without progress_percentage.
     * Binary completion: any finished quick-test attempt.
     */
    public function updateSituationProgress(int $situationId, int $userId): void
    {
        $situation = NegotiationSituation::find($situationId);
        if (!$situation) {
            return;
        }

        $latestAttempt = UserNegotiationSituationAttempt::where('user_id', $userId)
            ->where('negotiation_situation_id', $situationId)
            ->orderBy('started_at', 'desc')
            ->first();

        $hasAnyAttempt = $latestAttempt !== null;

        // Binary completion: finishing the quick test (any finished attempt). Never revoke.
        $isCompleted = UserNegotiationSituationAttempt::where('user_id', $userId)
            ->where('negotiation_situation_id', $situationId)
            ->where('status', 'finished')
            ->exists();

        $bestScore = UserNegotiationSituationAttempt::where('user_id', $userId)
            ->where('negotiation_situation_id', $situationId)
            ->where('status', 'finished')
            ->whereNotNull('score')
            ->max('score');

        $score = $bestScore;
        if ($score === null) {
            $score = $latestAttempt?->score;
        }
        if ($score === null) {
            $score = 0;
        }

        // Stored track_status stays narrow: completed|open (locked is runtime-only).
        $trackStatus = $isCompleted ? 'completed' : 'open';

        $progress = UserNegotiationSituationProgress::firstOrNew([
            'user_id' => $userId,
            'negotiation_situation_id' => $situationId,
        ]);

        $progress->track_status = $trackStatus;
        $progress->is_completed = $isCompleted;
        $progress->status = $isCompleted
            ? 'completed'
            : ($hasAnyAttempt ? 'in_progress' : 'not_started');
        $progress->score = $score;

        if (!$progress->started_at && $hasAnyAttempt) {
            $progress->started_at = $latestAttempt->started_at ?? now();
        }

        if ($isCompleted && !$progress->completed_at) {
            $progress->completed_at = now();
        }

        $progress->save();

        if ($isCompleted) {
            $this->checkAndUpdateNegotiationLevelCompletion(
                (int) $situation->negotiation_level_id,
                $userId
            );
        }
    }

    /**
     * Mirror TrackProgressService::checkAndUpdateLevelCompletion (L946–1054)
     * without UserCourse / course completion / certificates.
     */
    public function checkAndUpdateNegotiationLevelCompletion(int $levelId, int $userId): void
    {
        $level = NegotiationLevel::find($levelId);
        if (!$level) {
            return;
        }

        $situations = NegotiationSituation::where('negotiation_level_id', $levelId)
            ->orderBy('order_index')
            ->get();

        if ($situations->isEmpty()) {
            return;
        }

        $hasPublished = false;
        $allCompleted = true;

        foreach ($situations as $situation) {
            if (!$this->isSituationPublished($situation)) {
                continue;
            }

            $hasPublished = true;

            if (!$this->isSituationCompleted($situation, $userId)) {
                $allCompleted = false;
                break;
            }
        }

        if (!$hasPublished || !$allCompleted) {
            return;
        }

        $userLevelProgress = UserNegotiationLevelProgress::firstOrNew([
            'user_id' => $userId,
            'negotiation_level_id' => $levelId,
        ]);

        $oldStatus = $userLevelProgress->status;

        // Permanent guard: level stays completed even if new situations are added later.
        if ($userLevelProgress->status === 'completed') {
            return;
        }

        if ($userLevelProgress->status !== 'completed') {
            $userLevelProgress->status = 'completed';

            if (!$userLevelProgress->completed_at) {
                $userLevelProgress->completed_at = now();
            }
            if (!$userLevelProgress->started_at) {
                $userLevelProgress->started_at = now();
            }

            $totalScore = 0;
            $completedCount = 0;

            foreach ($situations as $situation) {
                if (!$this->isSituationPublished($situation)) {
                    continue;
                }

                $situationProgress = UserNegotiationSituationProgress::where('user_id', $userId)
                    ->where('negotiation_situation_id', $situation->id)
                    ->first();

                if ($situationProgress && $situationProgress->status === 'completed') {
                    $totalScore += $situationProgress->score ?? 0;
                    $completedCount++;
                }
            }

            $userLevelProgress->score = $completedCount > 0
                ? round($totalScore / $completedCount, 2)
                : 0;

            $userLevelProgress->save();

            if ($oldStatus !== 'completed') {
                Event::dispatch(new UserNegotiationLevelCompleted($userLevelProgress));
            }
        }
    }

    /**
     * Mirror TrackProgressService::isLevelCompleted (L1065–1116).
     */
    public function isNegotiationLevelCompleted(NegotiationLevel $level, int $userId): bool
    {
        $userLevelProgress = UserNegotiationLevelProgress::where('user_id', $userId)
            ->where('negotiation_level_id', $level->id)
            ->first();

        if ($userLevelProgress && $userLevelProgress->status === 'completed') {
            return true;
        }

        $situations = NegotiationSituation::where('negotiation_level_id', $level->id)
            ->orderBy('order_index')
            ->get();

        if ($situations->isEmpty()) {
            return false;
        }

        $hasPublished = false;

        foreach ($situations as $situation) {
            if (!$this->isSituationPublished($situation)) {
                continue;
            }

            $hasPublished = true;

            if (!$this->isSituationCompleted($situation, $userId)) {
                return false;
            }
        }

        return $hasPublished;
    }

    /**
     * Mirror TrackProgressService::getLevelAccessStatus (L1128–1198) without course parent.
     * Returns completed | open | locked. No subscription check at level layer.
     */
    public function getNegotiationLevelAccessStatus(NegotiationLevel $level, int $userId): string
    {
        if ($this->isNegotiationLevelCompleted($level, $userId)) {
            return 'completed';
        }

        $levels = NegotiationLevel::where('is_published', true)
            ->orderBy('order_index', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $previousLevel = null;
        $currentOrderIndex = $level->order_index;
        $currentId = $level->id;

        foreach ($levels as $candidate) {
            if ($candidate->id === $currentId) {
                continue;
            }

            $candidateOrderIndex = $candidate->order_index;

            if ($candidateOrderIndex < $currentOrderIndex) {
                if (!$previousLevel || $candidateOrderIndex > $previousLevel->order_index) {
                    $previousLevel = $candidate;
                } elseif ($candidateOrderIndex === $previousLevel->order_index) {
                    if ($candidate->id < $previousLevel->id) {
                        $previousLevel = $candidate;
                    }
                }
            } elseif ($candidateOrderIndex === $currentOrderIndex && $candidate->id < $currentId) {
                if (!$previousLevel) {
                    $previousLevel = $candidate;
                } elseif ($previousLevel->order_index === $currentOrderIndex) {
                    if ($candidate->id < $previousLevel->id) {
                        $previousLevel = $candidate;
                    }
                }
            }
        }

        if (!$previousLevel) {
            return 'open';
        }

        if ($this->isNegotiationLevelCompleted($previousLevel, $userId)) {
            return 'open';
        }

        return 'locked';
    }

    /**
     * Batch access/progress for published negotiation levels.
     *
     * @param  \Illuminate\Support\Collection<int, NegotiationLevel>  $levels
     * @return array<int, array{access_status: string, completed_situations: int, total_situations: int}>
     */
    public function loadProgressDataForNegotiationLevels($levels, int $userId): array
    {
        $result = [];

        if ($levels->isEmpty()) {
            return $result;
        }

        $levelIds = $levels->pluck('id')->all();

        $situationIdsByLevel = NegotiationSituation::query()
            ->whereIn('negotiation_level_id', $levelIds)
            ->where('is_published', true)
            ->get(['id', 'negotiation_level_id'])
            ->groupBy('negotiation_level_id');

        $allSituationIds = $situationIdsByLevel->flatten(1)->pluck('id')->all();
        $completedSituationIds = [];

        if (!empty($allSituationIds)) {
            $completedSituationIds = UserNegotiationSituationProgress::query()
                ->where('user_id', $userId)
                ->whereIn('negotiation_situation_id', $allSituationIds)
                ->where(function ($q) {
                    $q->where('is_completed', true)
                        ->orWhere('status', 'completed')
                        ->orWhere('track_status', 'completed');
                })
                ->pluck('negotiation_situation_id')
                ->all();

            $finishedFromAttempts = UserNegotiationSituationAttempt::query()
                ->where('user_id', $userId)
                ->whereIn('negotiation_situation_id', $allSituationIds)
                ->where('status', 'finished')
                ->pluck('negotiation_situation_id')
                ->unique()
                ->all();

            $completedSituationIds = array_values(array_unique(array_merge(
                $completedSituationIds,
                $finishedFromAttempts
            )));
        }

        $completedSet = array_flip($completedSituationIds);

        foreach ($levels as $level) {
            $levelSituations = $situationIdsByLevel->get($level->id, collect());
            $completed = 0;
            foreach ($levelSituations as $situation) {
                if (isset($completedSet[$situation->id])) {
                    $completed++;
                }
            }

            $result[$level->id] = [
                'access_status' => $this->getNegotiationLevelAccessStatus($level, $userId),
                'completed_situations' => $completed,
                'total_situations' => $levelSituations->count(),
            ];
        }

        return $result;
    }

    /**
     * Batch access statuses for situations within a level.
     *
     * @param  \Illuminate\Support\Collection<int, NegotiationSituation>  $situations
     * @return array<int, string> situation_id => access_status
     */
    public function loadAccessStatusesForSituations($situations, int $userId): array
    {
        $result = [];
        foreach ($situations as $situation) {
            $result[$situation->id] = $this->getSituationAccessStatus($situation, $userId);
        }

        return $result;
    }

    /**
     * in_progress = unfinished quick-test attempt exists, or summary status = in_progress.
     */
    private function hasInProgressSituation(NegotiationSituation $situation, int $userId): bool
    {
        $progress = UserNegotiationSituationProgress::where('user_id', $userId)
            ->where('negotiation_situation_id', $situation->id)
            ->first();

        if ($progress && $progress->status === 'in_progress' && !($progress->is_completed ?? false)) {
            return true;
        }

        return UserNegotiationSituationAttempt::where('user_id', $userId)
            ->where('negotiation_situation_id', $situation->id)
            ->where('status', 'in_progress')
            ->exists();
    }
}
