<?php

namespace App\Services;

use App\Events\UserCourseCompleted;
use App\Events\UserLevelCompleted;
use App\Models\Learning\Course;
use App\Models\Learning\Lesson;
use App\Models\Learning\Level;
use App\Models\Learning\LevelTrack;
use App\Models\Progress\UserLessonAttempt;
use App\Models\Progress\UserLessonProgress;
use App\Models\Progress\UserLessonQuestionAnswer;
use App\Models\Progress\UserLevelProgress;
use App\Models\Progress\UserCourse;
use App\Models\Scenarios\Scenario;
use App\Models\Scenarios\UserScenarioAttempt;
use App\Models\System\Certificate;
use Illuminate\Support\Facades\Event;

class TrackProgressService
{
    /**
     * Get track status: locked, open, or completed
     * Reads from stored data in database
     *
     * @param LevelTrack $levelTrack
     * @param int $userId
     * @return string
     */
    public function getTrackStatus(LevelTrack $levelTrack, int $userId): string
    {
        // Check if previous track is completed (for locked status)
        if (!$this->canAccessTrack($levelTrack, $userId)) {
            return 'locked';
        }

        // Get status from stored data
        if ($levelTrack->trackable instanceof Lesson) {
            $progress = UserLessonProgress::where('user_id', $userId)
                ->where('lesson_id', $levelTrack->trackable->id)
                ->first();
            
            if ($progress && $progress->track_status) {
                return $progress->track_status;
            }
        } elseif ($levelTrack->trackable instanceof Scenario) {
            $attempt = UserScenarioAttempt::where('user_id', $userId)
                ->where('scenario_id', $levelTrack->trackable->id)
                ->orderBy('started_at', 'desc')
                ->first();
            
            if ($attempt && $attempt->track_status) {
                return $attempt->track_status;
            }
        }

        // Fallback: calculate from completion status
        if ($this->isTrackCompleted($levelTrack->trackable, $userId)) {
            return 'completed';
        }

        return 'open';
    }

    /**
     * Check if user can access a track (previous track is completed)
     * Uses stored is_completed flag for better performance
     * Skips unpublished tracks when finding the previous track
     *
     * @param LevelTrack $levelTrack
     * @param int $userId
     * @return bool
     */
    public function canAccessTrack(LevelTrack $levelTrack, int $userId): bool
    {
        // Load all tracks in the level to find the previous published track
        $allTracks = LevelTrack::where('level_id', $levelTrack->level_id)
            ->with('trackable')
            ->orderBy('order_index', 'desc')
            ->get();

        // Find the first published track before the current one
        $previousTrack = null;
        foreach ($allTracks as $track) {
            if ($track->order_index >= $levelTrack->order_index) {
                continue; // Skip current track and tracks after it
            }

            // Ensure trackable is loaded
            if (!$track->relationLoaded('trackable')) {
                $track->load('trackable');
            }

            // Skip unpublished tracks
            if ($track->trackable && $this->isTrackablePublished($track->trackable)) {
                $previousTrack = $track;
                break;
            }
        }

        if (!$previousTrack) {
            return true; // This is the first published track (or no previous published track)
        }

        // Check if previous track is completed using stored data
        return $this->isTrackCompleted($previousTrack->trackable, $userId);
    }

    /**
     * Check if a trackable (lesson or scenario) is published
     *
     * @param mixed $trackable (Lesson or Scenario)
     * @return bool
     */
    public function isTrackablePublished($trackable): bool
    {
        if ($trackable instanceof Lesson) {
            return $trackable->is_published ?? false;
        } elseif ($trackable instanceof Scenario) {
            return $trackable->is_published ?? false;
        }
        return false;
    }

    /**
     * Check if a trackable (lesson or scenario) is completed
     * Completed means: first attempt is finished (status = finished)
     *
     * @param mixed $trackable (Lesson or Scenario)
     * @param int $userId
     * @return bool
     */
    public function isTrackCompleted($trackable, int $userId): bool
    {
        if ($trackable instanceof Lesson) {
            return $this->isLessonCompleted($trackable, $userId);
        } elseif ($trackable instanceof Scenario) {
            return $this->isScenarioCompleted($trackable, $userId);
        }

        return false;
    }

    /**
     * Check if lesson is completed
     * يتحقق من عدة مصادر: is_completed, status, progress_percentage, finished attempts
     *
     * @param Lesson $lesson
     * @param int $userId
     * @return bool
     */
    private function isLessonCompleted(Lesson $lesson, int $userId): bool
    {
        $progress = UserLessonProgress::where('user_id', $userId)
            ->where('lesson_id', $lesson->id)
            ->first();

        if ($progress) {
            // 1. التحقق من is_completed flag
            if (isset($progress->is_completed) && $progress->is_completed === true) {
                return true;
            }
            
            // 2. التحقق من status = 'completed'
            if (isset($progress->status) && $progress->status === 'completed') {
                return true;
            }
            
            // 3. التحقق من progress_percentage >= 100
            if (isset($progress->progress_percentage) && (float)$progress->progress_percentage >= 100) {
                return true;
            }
            
            // 4. التحقق من track_status = 'completed'
            if (isset($progress->track_status) && $progress->track_status === 'completed') {
                return true;
            }
        }

        // Fallback: check first finished attempt
        $firstFinishedAttempt = UserLessonAttempt::where('user_id', $userId)
            ->where('lesson_id', $lesson->id)
            ->where('status', 'finished')
            ->orderBy('finished_at', 'asc')
            ->first();

        return $firstFinishedAttempt !== null;
    }

    /**
     * Check if scenario is completed
     * يتحقق من عدة مصادر: is_completed, progress_percentage, status, finished attempts
     *
     * @param Scenario $scenario
     * @param int $userId
     * @return bool
     */
    private function isScenarioCompleted(Scenario $scenario, int $userId): bool
    {
        // Check latest attempt for completion flags
        $attempt = UserScenarioAttempt::where('user_id', $userId)
            ->where('scenario_id', $scenario->id)
            ->orderBy('started_at', 'desc')
            ->first();

        if ($attempt) {
            // 1. التحقق من is_completed flag
            if (isset($attempt->is_completed) && $attempt->is_completed === true) {
                return true;
            }
            
            // 2. التحقق من progress_percentage >= 100
            if (isset($attempt->progress_percentage) && (float)$attempt->progress_percentage >= 100) {
                return true;
            }
            
            // 3. التحقق من status = 'finished'
            if (isset($attempt->status) && $attempt->status === 'finished') {
                return true;
            }
            
            // 4. التحقق من track_status = 'completed'
            if (isset($attempt->track_status) && $attempt->track_status === 'completed') {
                return true;
            }
        }

        // Fallback: check first finished attempt
        $firstFinishedAttempt = UserScenarioAttempt::where('user_id', $userId)
            ->where('scenario_id', $scenario->id)
            ->where('status', 'finished')
            ->orderBy('finished_at', 'asc')
            ->first();

        return $firstFinishedAttempt !== null;
    }

    /**
     * Get progress percentage for a trackable (lesson or scenario)
     *
     * @param mixed $trackable (Lesson or Scenario)
     * @param int $userId
     * @return float (0-100)
     */
    public function getProgressPercentage($trackable, int $userId): float
    {
        if ($trackable instanceof Lesson) {
            return $this->getLessonProgressPercentage($trackable, $userId);
        } elseif ($trackable instanceof Scenario) {
            return $this->getScenarioProgressPercentage($trackable, $userId);
        }

        return 0;
    }

    /**
     * Get lesson progress percentage
     * Reads from stored progress_percentage in user_lesson_progress or user_lesson_attempts
     *
     * @param Lesson $lesson
     * @param int $userId
     * @return float
     */
    private function getLessonProgressPercentage(Lesson $lesson, int $userId): float
    {
        // First try to get from user_lesson_progress
        $progress = UserLessonProgress::where('user_id', $userId)
            ->where('lesson_id', $lesson->id)
            ->first();

        if ($progress && isset($progress->progress_percentage)) {
            return (float) $progress->progress_percentage;
        }

        // Fallback: get from latest attempt
        $attempt = UserLessonAttempt::where('user_id', $userId)
            ->where('lesson_id', $lesson->id)
            ->orderBy('started_at', 'desc')
            ->first();

        if ($attempt && isset($attempt->progress_percentage)) {
            return (float) $attempt->progress_percentage;
        }

        return 0;
    }

    /**
     * Get scenario progress percentage
     * Reads from stored progress_percentage in user_scenario_attempts
     *
     * @param Scenario $scenario
     * @param int $userId
     * @return float
     */
    private function getScenarioProgressPercentage(Scenario $scenario, int $userId): float
    {
        // Get the current or latest attempt
        $attempt = UserScenarioAttempt::where('user_id', $userId)
            ->where('scenario_id', $scenario->id)
            ->orderBy('started_at', 'desc')
            ->first();

        if ($attempt && isset($attempt->progress_percentage)) {
            return (float) $attempt->progress_percentage;
        }

        return 0;
    }

    /**
     * Get previous published track for a given level track
     * Skips unpublished tracks
     *
     * @param LevelTrack $levelTrack
     * @return LevelTrack|null
     */
    public function getPreviousTrack(LevelTrack $levelTrack): ?LevelTrack
    {
        // Load all tracks in the level to find the previous published track
        $allTracks = LevelTrack::where('level_id', $levelTrack->level_id)
            ->with('trackable')
            ->orderBy('order_index', 'desc')
            ->get();

        // Find the first published track before the current one
        foreach ($allTracks as $track) {
            if ($track->order_index >= $levelTrack->order_index) {
                continue; // Skip current track and tracks after it
            }

            // Ensure trackable is loaded
            if (!$track->relationLoaded('trackable')) {
                $track->load('trackable');
            }

            // Skip unpublished tracks
            if ($track->trackable && $this->isTrackablePublished($track->trackable)) {
                return $track;
            }
        }

        return null; // No previous published track found
    }

    /**
     * Update lesson progress and status in database
     *
     * @param Lesson $lesson
     * @param int $userId
     * @param float $progressPercentage
     * @param string $trackStatus
     * @param bool $isCompleted
     * @return void
     */
    public function updateLessonProgress(Lesson $lesson, int $userId, float $progressPercentage, string $trackStatus, bool $isCompleted): void
    {
        // Update or create user_lesson_progress
        $progress = UserLessonProgress::firstOrNew([
            'user_id' => $userId,
            'lesson_id' => $lesson->id,
        ]);

        $progress->progress_percentage = $progressPercentage;
        $progress->track_status = $trackStatus;
        $progress->is_completed = $isCompleted;
        $progress->status = $isCompleted ? 'completed' : ($progressPercentage > 0 ? 'in_progress' : 'not_started');

        // Set score to 0 if not set (required field)
        if ($progress->score === null) {
            $progress->score = 0;
        }

        // Set started_at if not set
        if (!$progress->started_at && $progressPercentage > 0) {
            $progress->started_at = now();
        }

        // Set completed_at if completed
        if ($isCompleted && !$progress->completed_at) {
            $progress->completed_at = now();
        }

        $progress->save();

        // Also update latest attempt's progress_percentage
        $latestAttempt = UserLessonAttempt::where('user_id', $userId)
            ->where('lesson_id', $lesson->id)
            ->orderBy('started_at', 'desc')
            ->first();

        if ($latestAttempt) {
            $latestAttempt->progress_percentage = $progressPercentage;
            $latestAttempt->save();
        }

        // التحقق من إكمال جميع الدروس في المستوى وتحديث UserLevelProgress
        if ($isCompleted) {
            $this->checkAndUpdateLevelCompletion($lesson->level_id, $userId);
        }
    }

    /**
     * Update scenario progress and status in database
     *
     * @param Scenario $scenario
     * @param int $userId
     * @param float $progressPercentage
     * @param string $trackStatus
     * @param bool $isCompleted
     * @return void
     */
    public function updateScenarioProgress(Scenario $scenario, int $userId, float $progressPercentage, string $trackStatus, bool $isCompleted): void
    {
        // Update latest attempt
        $latestAttempt = UserScenarioAttempt::where('user_id', $userId)
            ->where('scenario_id', $scenario->id)
            ->orderBy('started_at', 'desc')
            ->first();

        if ($latestAttempt) {
            $latestAttempt->progress_percentage = $progressPercentage;
            $latestAttempt->track_status = $trackStatus;
            $latestAttempt->is_completed = $isCompleted;
            $latestAttempt->save();
        }
    }

    /**
     * Calculate and update lesson progress percentage
     *
     * @param Lesson $lesson
     * @param int $userId
     * @param UserLessonAttempt|null $attempt
     * @return float
     */
    public function calculateAndUpdateLessonProgress(Lesson $lesson, int $userId, ?UserLessonAttempt $attempt = null): float
    {
        if (!$attempt) {
            $attempt = UserLessonAttempt::where('user_id', $userId)
                ->where('lesson_id', $lesson->id)
                ->orderBy('started_at', 'desc')
                ->first();
        }

        if (!$attempt) {
            $progressPercentage = 0;
        } else {
            $progress = 0;

            // 30% for video watched
            if ($attempt->video_watched) {
                $progress += 30;
            }

            // 70% for answered questions
            $totalQuestions = $lesson->lessonQuestions()->count();
            if ($totalQuestions > 0) {
                $answeredCount = UserLessonQuestionAnswer::where('attempt_id', $attempt->id)
                    ->count();
                $questionsProgress = ($answeredCount / $totalQuestions) * 70;
                $progress += $questionsProgress;
            } else {
                // If no questions, video watching is 100% of progress
                $progress = $attempt->video_watched ? 100 : 0;
            }

            // If attempt is finished, it's 100%
            if ($attempt->status === 'finished') {
                $progress = 100;
            }

            $progressPercentage = min(round($progress, 2), 100);
        }

        // Determine track status
        $canAccess = true;
        $levelTrack = LevelTrack::where('trackable_id', $lesson->id)
            ->where('trackable_type', Lesson::class)
            ->first();

        if ($levelTrack) {
            $canAccess = $this->canAccessTrack($levelTrack, $userId);
        }

        $trackStatus = 'open';
        if (!$canAccess) {
            $trackStatus = 'locked';
        } elseif ($progressPercentage >= 100) {
            $trackStatus = 'completed';
        }

        $isCompleted = $progressPercentage >= 100;

        // Update stored values
        $this->updateLessonProgress($lesson, $userId, $progressPercentage, $trackStatus, $isCompleted);

        return $progressPercentage;
    }

    /**
     * Calculate and update scenario progress percentage
     *
     * @param Scenario $scenario
     * @param int $userId
     * @param UserScenarioAttempt|null $attempt
     * @return float
     */
    public function calculateAndUpdateScenarioProgress(Scenario $scenario, int $userId, ?UserScenarioAttempt $attempt = null): float
    {
        if (!$attempt) {
            $attempt = UserScenarioAttempt::where('user_id', $userId)
                ->where('scenario_id', $scenario->id)
                ->orderBy('started_at', 'desc')
                ->first();
        }

        if (!$attempt) {
            $progressPercentage = 0;
        } else {
            $progress = 0;

            // 30% for description read
            if ($attempt->description_read) {
                $progress += 30;
            }

            // 70% when finished
            if ($attempt->status === 'finished') {
                $progress += 70;
            }

            $progressPercentage = min(round($progress, 2), 100);
        }

        // Determine track status
        $canAccess = true;
        $levelTrack = LevelTrack::where('trackable_id', $scenario->id)
            ->where('trackable_type', Scenario::class)
            ->first();

        if ($levelTrack) {
            $canAccess = $this->canAccessTrack($levelTrack, $userId);
        }

        $trackStatus = 'open';
        if (!$canAccess) {
            $trackStatus = 'locked';
        } elseif ($progressPercentage >= 100) {
            $trackStatus = 'completed';
        }

        $isCompleted = $progressPercentage >= 100;

        // Update stored values
        $this->updateScenarioProgress($scenario, $userId, $progressPercentage, $trackStatus, $isCompleted);

        // التحقق من إكمال جميع السيناريوهات/الدروس في المستوى وتحديث UserLevelProgress
        if ($isCompleted) {
            $levelId = $scenario->level_id ?? null;
            if ($levelId) {
                $this->checkAndUpdateLevelCompletion($levelId, $userId);
            }
        }

        return $progressPercentage;
    }

    /**
     * Load all progress data for level tracks in batch (optimized for collections)
     *
     * @param \Illuminate\Database\Eloquent\Collection $levelTracks
     * @param int $userId
     * @return array
     */
    public function loadProgressDataForTracks($levelTracks, int $userId): array
    {
        // Convert to collection if array
        if (is_array($levelTracks)) {
            $levelTracks = collect($levelTracks);
        }

        if ($levelTracks->isEmpty()) {
            return [];
        }

        $levelIds = $levelTracks->pluck('level_id')->unique()->toArray();
        $lessonIds = [];
        $scenarioIds = [];

        foreach ($levelTracks as $track) {
            if ($track->trackable_type === Lesson::class) {
                $lessonIds[] = $track->trackable_id;
            } elseif ($track->trackable_type === Scenario::class) {
                $scenarioIds[] = $track->trackable_id;
            }
        }

        // Load all lesson progress data in one query
        $lessonProgressMap = [];
        if (!empty($lessonIds)) {
            $lessonProgresses = UserLessonProgress::where('user_id', $userId)
                ->whereIn('lesson_id', $lessonIds)
                ->get()
                ->keyBy('lesson_id');

            foreach ($lessonProgresses as $progress) {
                $lessonProgressMap[$progress->lesson_id] = [
                    'progress_percentage' => (float) ($progress->progress_percentage ?? 0),
                    'track_status' => $progress->track_status ?? 'open',
                    'is_completed' => (bool) ($progress->is_completed ?? false),
                ];
            }
        }

        // Load all scenario progress data in one query
        $scenarioProgressMap = [];
        if (!empty($scenarioIds)) {
            // Get latest attempts for each scenario
            $scenarioAttempts = UserScenarioAttempt::where('user_id', $userId)
                ->whereIn('scenario_id', $scenarioIds)
                ->orderBy('scenario_id')
                ->orderBy('started_at', 'desc')
                ->get()
                ->groupBy('scenario_id')
                ->map(function ($attempts) {
                    return $attempts->first();
                });

            foreach ($scenarioAttempts as $attempt) {
                $scenarioProgressMap[$attempt->scenario_id] = [
                    'progress_percentage' => (float) ($attempt->progress_percentage ?? 0),
                    'track_status' => $attempt->track_status ?? 'open',
                    'is_completed' => (bool) ($attempt->is_completed ?? false),
                ];
            }
        }

        // Load completion status for all previous tracks (for canAccess calculation)
        // Load all tracks in batch to find previous ones
        $allTracksInLevels = LevelTrack::whereIn('level_id', $levelIds)
            ->with('trackable') // Load trackable relationship
            ->orderBy('level_id')
            ->orderBy('order_index')
            ->get()
            ->groupBy('level_id');

        $previousTrackCompletionMap = [];
        foreach ($levelTracks as $track) {
            $tracksInLevel = $allTracksInLevels->get($track->level_id);
            if ($tracksInLevel) {
                // Find the first published track before the current one
                // Sort by order_index descending to find the closest previous track first
                $previousTrack = null;
                $sortedTracks = $tracksInLevel->sortByDesc('order_index');
                
                foreach ($sortedTracks as $potentialPrevious) {
                    if ($potentialPrevious->order_index >= $track->order_index) {
                        continue; // Skip current track and tracks after it
                    }

                    // Ensure trackable is loaded
                    if (!$potentialPrevious->relationLoaded('trackable')) {
                        $potentialPrevious->load('trackable');
                    }

                    // Skip unpublished tracks
                    if ($potentialPrevious->trackable && $this->isTrackablePublished($potentialPrevious->trackable)) {
                        $previousTrack = $potentialPrevious;
                        break; // Found the first published track before current
                    }
                }

                if ($previousTrack) {
                    $previousTrackCompletionMap[$track->id] = $previousTrack;
                }
            }
        }

        // Get completion status for all previous tracks in batch
        $previousTrackIds = collect($previousTrackCompletionMap)->map(function ($track) {
            return $track->trackable_id;
        })->toArray();
        $previousTrackTypes = collect($previousTrackCompletionMap)->map(function ($track) {
            return $track->trackable_type;
        })->toArray();

        $previousLessonIds = [];
        $previousScenarioIds = [];
        foreach ($previousTrackCompletionMap as $currentTrackId => $prevTrack) {
            if ($prevTrack->trackable_type === Lesson::class) {
                $previousLessonIds[$currentTrackId] = $prevTrack->trackable_id;
            } elseif ($prevTrack->trackable_type === Scenario::class) {
                $previousScenarioIds[$currentTrackId] = $prevTrack->trackable_id;
            }
        }

        $previousCompletionMap = [];
        if (!empty($previousLessonIds)) {
            $previousLessonProgresses = UserLessonProgress::where('user_id', $userId)
                ->whereIn('lesson_id', array_values($previousLessonIds))
                ->get()
                ->keyBy('lesson_id');

            foreach ($previousLessonIds as $currentTrackId => $lessonId) {
                $progress = $previousLessonProgresses->get($lessonId);
                // If progress exists, use is_completed flag
                // If progress doesn't exist, check directly using isTrackCompleted
                if ($progress) {
                    $previousCompletionMap[$currentTrackId] = (bool) ($progress->is_completed ?? false);
                } else {
                    // Progress not found, check directly (lesson might not have been started)
                    // Load the lesson and check if it's completed
                    $previousTrack = $previousTrackCompletionMap[$currentTrackId] ?? null;
                    if ($previousTrack) {
                        // Ensure trackable is loaded
                        if (!$previousTrack->relationLoaded('trackable')) {
                            $previousTrack->load('trackable');
                        }
                        
                        if ($previousTrack->trackable) {
                            $previousCompletionMap[$currentTrackId] = $this->isTrackCompleted($previousTrack->trackable, $userId);
                        } else {
                            // If trackable is null, assume not completed
                            $previousCompletionMap[$currentTrackId] = false;
                        }
                    } else {
                        // If we can't determine, assume not completed
                        $previousCompletionMap[$currentTrackId] = false;
                    }
                }
            }
        }

        if (!empty($previousScenarioIds)) {
            $previousScenarioAttempts = UserScenarioAttempt::where('user_id', $userId)
                ->whereIn('scenario_id', array_values($previousScenarioIds))
                ->orderBy('scenario_id')
                ->orderBy('started_at', 'desc')
                ->get()
                ->groupBy('scenario_id')
                ->map(function ($attempts) {
                    return $attempts->first();
                });

            foreach ($previousScenarioIds as $currentTrackId => $scenarioId) {
                $attempt = $previousScenarioAttempts->get($scenarioId);
                $previousCompletionMap[$currentTrackId] = $attempt && ($attempt->is_completed ?? false);
            }
        }

        // Build result map
        $result = [];
        foreach ($levelTracks as $track) {
            $trackableId = $track->trackable_id;
            
            // Get progress data
            if ($track->trackable_type === Lesson::class) {
                $progressData = $lessonProgressMap[$trackableId] ?? [
                    'progress_percentage' => 0,
                    'track_status' => 'open',
                    'is_completed' => false,
                ];
            } else {
                $progressData = $scenarioProgressMap[$trackableId] ?? [
                    'progress_percentage' => 0,
                    'track_status' => 'open',
                    'is_completed' => false,
                ];
            }

            // Check if accessible (previous published track completed)
            $hasPreviousTrack = isset($previousTrackCompletionMap[$track->id]);
            
            // If no previous track in map, check if there's actually a previous published track
            if (!$hasPreviousTrack) {
                $tracksInLevel = $allTracksInLevels->get($track->level_id);
                if ($tracksInLevel) {
                    // Check if there's any published track before this one
                    // Sort by order_index descending to find the closest previous track first
                    $sortedTracks = $tracksInLevel->sortByDesc('order_index');
                    foreach ($sortedTracks as $potentialPrevious) {
                        if ($potentialPrevious->order_index >= $track->order_index) {
                            continue; // Skip current track and tracks after it
                        }

                        // Ensure trackable is loaded
                        if (!$potentialPrevious->relationLoaded('trackable')) {
                            $potentialPrevious->load('trackable');
                        }

                        // If we find a published track before this one, it means we should have found it earlier
                        // This shouldn't happen, but if it does, treat it as having a previous track
                        if ($potentialPrevious->trackable && $this->isTrackablePublished($potentialPrevious->trackable)) {
                            $hasPreviousTrack = true;
                            // Add it to the map and check completion
                            $previousTrackCompletionMap[$track->id] = $potentialPrevious;
                            $isAccessible = $this->isTrackCompleted($potentialPrevious->trackable, $userId);
                            $previousCompletionMap[$track->id] = $isAccessible;
                            break;
                        }
                    }
                }
            }
            
            if ($hasPreviousTrack) {
                // There is a previous published track, check if it's completed
                if (isset($previousCompletionMap[$track->id])) {
                    // Use cached completion status
                    $isAccessible = $previousCompletionMap[$track->id];
                } else {
                    // Completion status not in cache, check directly
                    $previousTrack = $previousTrackCompletionMap[$track->id];
                    
                    // Ensure trackable is loaded
                    if (!$previousTrack->relationLoaded('trackable')) {
                        $previousTrack->load('trackable');
                    }
                    
                    if ($previousTrack->trackable) {
                        $isAccessible = $this->isTrackCompleted($previousTrack->trackable, $userId);
                    } else {
                        // If trackable is null, assume not completed
                        $isAccessible = false;
                    }
                    
                    // Cache it for future use
                    $previousCompletionMap[$track->id] = $isAccessible;
                }
            } else {
                // No previous published track - this is the first published track in level, always accessible
                $isAccessible = true;
            }

            // Determine final status
            // Completed tracks remain accessible even if previous track is not completed
            // (This handles the case where an unpublished track becomes published after user completed later tracks)
            if ($progressData['is_completed']) {
                // Completed tracks always remain completed and accessible
                $status = 'completed';
                $isAccessible = true; // Override accessibility for completed tracks
            } elseif (!$isAccessible) {
                // Not accessible and not completed = locked
                $status = 'locked';
            } elseif (!$hasPreviousTrack) {
                // First published track in level is always open (unless completed)
                $status = 'open';
            } elseif (isset($progressData['track_status']) && $progressData['track_status']) {
                // Use stored status if available and accessible
                $status = $progressData['track_status'];
            } else {
                // Default to open if accessible and not completed
                $status = 'open';
            }
            
            // If track is in progress (has progress but not completed), allow access even if previous is not completed
            // This only applies if the user has actually started the track (progress > 0)
            if ($progressData['progress_percentage'] > 0 && !$progressData['is_completed'] && !$isAccessible) {
                // In-progress tracks remain accessible (user has started it, so they can continue)
                $isAccessible = true;
                if ($status === 'locked') {
                    $status = 'open';
                }
            }

            $result[$track->id] = [
                'status' => $status,
                'progress_percentage' => $progressData['progress_percentage'],
                'is_accessible' => $isAccessible,
            ];
        }

        return $result;
    }

    /**
     * التحقق من إكمال جميع الدروس في المستوى وتحديث UserLevelProgress
     * وإطلاق Event عند إكمال المستوى
     */
    public function checkAndUpdateLevelCompletion(int $levelId, int $userId): void
    {
        $level = Level::find($levelId);
        if (!$level) {
            return;
        }

        // جلب جميع الدروس والسيناريوهات في المستوى
        $levelTracks = LevelTrack::where('level_id', $levelId)
            ->with('trackable')
            ->orderBy('order_index')
            ->get();

        if ($levelTracks->isEmpty()) {
            return;
        }

        // التحقق من أن جميع الدروس والسيناريوهات مكتملة
        $allCompleted = true;
        foreach ($levelTracks as $track) {
            if (!$this->isTrackablePublished($track->trackable)) {
                continue; // تخطي الدروس غير المنشورة
            }

            if (!$this->isTrackCompleted($track->trackable, $userId)) {
                $allCompleted = false;
                break;
            }
        }

        if (!$allCompleted) {
            return; // بعض الدروس غير مكتملة
        }

        // إنشاء أو تحديث UserCourse تلقائياً عند إكمال المستوى
        // هذا يضمن أن المستخدم يعتبر مسجل في الكورس عند إكمال مستوى
        if ($level->course_id) {
            $userCourse = UserCourse::firstOrNew([
                'user_id' => $userId,
                'course_id' => $level->course_id,
            ]);

            if (!$userCourse->exists) {
                // إنشاء UserCourse جديد مع status = 'active'
                $userCourse->status = 'active';
                if (!$userCourse->started_at) {
                    $userCourse->started_at = now();
                }
                $userCourse->save();
            }
        }

        // تحديث أو إنشاء UserLevelProgress
        $userLevelProgress = UserLevelProgress::firstOrNew([
            'user_id' => $userId,
            'level_id' => $levelId,
        ]);

        $oldStatus = $userLevelProgress->status;

        // إذا كان المستوى مكتملاً بالفعل، لا نعيد التحقق ولا نغير شيئاً
        // المستوى يبقى مكتملاً نهائياً حتى لو أضيفت دروس/سيناريوهات جديدة
        if ($userLevelProgress->status === 'completed') {
            return; // المستوى مكتمل نهائياً، لا تغيير
        }

        // تحديث الحالة إلى completed
        if ($userLevelProgress->status !== 'completed') {
            $userLevelProgress->status = 'completed';
            if (!$userLevelProgress->completed_at) {
                $userLevelProgress->completed_at = now();
            }
            if (!$userLevelProgress->started_at) {
                $userLevelProgress->started_at = now();
            }

            // حساب النتيجة (متوسط نتائج الدروس)
            $lessons = $level->lessons()->get();
            $totalScore = 0;
            $completedLessons = 0;
            
            foreach ($lessons as $lesson) {
                $lessonProgress = UserLessonProgress::where('user_id', $userId)
                    ->where('lesson_id', $lesson->id)
                    ->first();
                
                if ($lessonProgress && $lessonProgress->status === 'completed') {
                    $totalScore += $lessonProgress->score ?? 0;
                    $completedLessons++;
                }
            }

            if ($completedLessons > 0) {
                $userLevelProgress->score = round($totalScore / $completedLessons, 2);
            } else {
                $userLevelProgress->score = 0;
            }

            $userLevelProgress->save();

            // إطلاق Event عند إكمال المستوى لأول مرة
            if ($oldStatus !== 'completed') {
                Event::dispatch(new UserLevelCompleted($userLevelProgress));
            }

            // التحقق من إكمال جميع المستويات في الكورس وإطلاق Event
            $this->checkAndUpdateCourseCompletion($level->course_id, $userId);
        }
    }

    /**
     * التحقق من إكمال المستوى
     * إذا كان status = 'completed' → المستوى مكتمل نهائياً
     * وإلا نتحقق من جميع الدروس والسيناريوهات المنشورة
     *
     * @param Level $level
     * @param int $userId
     * @return bool
     */
    public function isLevelCompleted(Level $level, int $userId): bool
    {
        // التحقق من UserLevelProgress أولاً
        $userLevelProgress = UserLevelProgress::where('user_id', $userId)
            ->where('level_id', $level->id)
            ->first();

        // إذا كان المستوى مكتملاً بالفعل (status = completed)، يعتبر مكتملاً نهائياً
        if ($userLevelProgress && $userLevelProgress->status === 'completed') {
            return true;
        }

        // إذا لم يكن مكتملاً، نتحقق من جميع الدروس والسيناريوهات المنشورة
        $levelTracks = LevelTrack::where('level_id', $level->id)
            ->with('trackable')
            ->orderBy('order_index')
            ->get();

        if ($levelTracks->isEmpty()) {
            return false;
        }

        // التحقق من أن جميع الدروس والسيناريوهات المنشورة مكتملة
        foreach ($levelTracks as $track) {
            // تخطي الدروس غير المنشورة
            if (!$this->isTrackablePublished($track->trackable)) {
                continue;
            }

            // التحقق من الإكمال
            if (!$this->isTrackCompleted($track->trackable, $userId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * تحديد حالة الوصول للمستوى
     * locked: المستوى السابق غير مكتمل
     * open: المستوى يمكن الوصول إليه
     * completed: المستوى مكتمل
     *
     * @param Level $level
     * @param int $userId
     * @return string
     */
    public function getLevelAccessStatus(Level $level, int $userId): string
    {
        // إذا كان المستوى مكتملاً، يعتبر completed
        if ($this->isLevelCompleted($level, $userId)) {
            return 'completed';
        }

        // إذا لم يكن المستوى مكتملاً، نتحقق من المستوى السابق
        if (!$level->course_id) {
            return 'open'; // إذا لم يكن له كورس، يكون مفتوح
        }

        // جلب جميع المستويات في الكورس مرتبة حسب order_index
        $levels = Level::where('course_id', $level->course_id)
            ->where('is_published', true)
            ->orderBy('order_index', 'asc')
            ->get();

        // العثور على المستوى السابق (أقل order_index)
        $previousLevel = null;
        foreach ($levels as $courseLevel) {
            if ($courseLevel->order_index < $level->order_index) {
                $previousLevel = $courseLevel;
            } else {
                break;
            }
        }

        // إذا لم يكن هناك مستوى سابق، المستوى مفتوح
        if (!$previousLevel) {
            return 'open';
        }

        // التحقق من إكمال المستوى السابق
        if ($this->isLevelCompleted($previousLevel, $userId)) {
            return 'open';
        }

        return 'locked';
    }

    /**
     * الحصول على شهادة المستوى للمستخدم
     *
     * @param Level $level
     * @param int $userId
     * @return Certificate|null
     */
    public function getUserCertificateForLevel(Level $level, int $userId): ?Certificate
    {
        if (!$level->course_id) {
            return null;
        }

        return Certificate::where('user_id', $userId)
            ->where('course_id', $level->course_id)
            ->where('level_id', $level->id)
            ->whereNotNull('issued_at')
            ->first();
    }

    /**
     * التحقق من إكمال جميع المستويات في الكورس وتحديث UserCourse
     * وإطلاق Event عند إكمال الكورس
     */
    public function checkAndUpdateCourseCompletion(int $courseId, int $userId): void
    {
        $course = Course::find($courseId);
        if (!$course) {
            return;
        }

        // جلب جميع المستويات في الكورس
        $levels = $course->levels()->orderBy('level_number')->get();
        if ($levels->isEmpty()) {
            return;
        }

        // التحقق من أن جميع المستويات مكتملة
        $allLevelsCompleted = true;
        foreach ($levels as $level) {
            $userLevelProgress = UserLevelProgress::where('user_id', $userId)
                ->where('level_id', $level->id)
                ->first();

            if (!$userLevelProgress || $userLevelProgress->status !== 'completed') {
                $allLevelsCompleted = false;
                break;
            }
        }

        if (!$allLevelsCompleted) {
            return; // بعض المستويات غير مكتملة
        }

        // تحديث أو إنشاء UserCourse
        $userCourse = UserCourse::firstOrNew([
            'user_id' => $userId,
            'course_id' => $courseId,
        ]);

        $oldStatus = $userCourse->status;

        // تحديث الحالة إلى completed
        if ($userCourse->status !== 'completed') {
            $userCourse->status = 'completed';
            if (!$userCourse->completed_at) {
                $userCourse->completed_at = now();
            }
            if (!$userCourse->started_at) {
                $userCourse->started_at = now();
            }

            $userCourse->save();

            // إطلاق Event عند إكمال الكورس لأول مرة
            if ($oldStatus !== 'completed') {
                Event::dispatch(new UserCourseCompleted($userCourse));
            }
        }
    }

    /**
     * تحميل بيانات التقدم للمستويات بشكل batch
     * لتحسين الأداء عند جلب قائمة المستويات
     * يستخدم batch loading لتقليل عدد queries إلى الحد الأدنى
     *
     * @param \Illuminate\Support\Collection $levels
     * @param int $userId
     * @return array
     */
    public function loadProgressDataForLevels($levels, int $userId): array
    {
        $result = [];

        if ($levels->isEmpty()) {
            return $result;
        }

        $levelIds = $levels->pluck('id')->toArray();

        // 1. جلب جميع UserLevelProgress للمستويات (batch query)
        $userLevelProgressMap = UserLevelProgress::where('user_id', $userId)
            ->whereIn('level_id', $levelIds)
            ->get()
            ->keyBy('level_id');

        // 2. جلب جميع الشهادات للمستويات (batch query)
        $certificatesMap = Certificate::where('user_id', $userId)
            ->whereIn('level_id', $levelIds)
            ->whereNotNull('issued_at')
            ->get()
            ->keyBy('level_id');

        // 3. جلب جميع levelTracks لجميع المستويات بشكل batch (query واحد)
        $allLevelTracks = LevelTrack::whereIn('level_id', $levelIds)
            ->with('trackable')
            ->orderBy('level_id')
            ->orderBy('order_index')
            ->get()
            ->groupBy('level_id');

        // 4. جلب جميع المستويات في الكورسات (للتحقق من المستوى السابق) - batch query
        $courseIds = $levels->whereNotNull('course_id')->pluck('course_id')->unique()->toArray();
        $allCourseLevels = collect();
        $allCourseLevelTracks = collect();
        
        if (!empty($courseIds)) {
            $allCourseLevels = Level::whereIn('course_id', $courseIds)
                ->where('is_published', true)
                ->orderBy('course_id')
                ->orderBy('order_index', 'asc')
                ->get()
                ->groupBy('course_id');

            // جلب جميع levelTracks لجميع مستويات الكورسات بشكل batch
            $courseLevelIds = $allCourseLevels->flatten()->pluck('id')->toArray();
            if (!empty($courseLevelIds)) {
                $allCourseLevelTracks = LevelTrack::whereIn('level_id', $courseLevelIds)
                    ->with('trackable')
                    ->orderBy('level_id')
                    ->orderBy('order_index')
                    ->get()
                    ->groupBy('level_id');
            }
        }

        // 5. تحميل بيانات التقدم للدروس والسيناريوهات بشكل batch
        $allTracks = $allLevelTracks->flatten();
        $allCourseTracks = $allCourseLevelTracks->flatten();
        $combinedTracks = $allTracks->merge($allCourseTracks)->unique('id');

        // فصل الدروس عن السيناريوهات
        $lessonIds = [];
        $scenarioIds = [];
        
        foreach ($combinedTracks as $track) {
            if ($track->trackable_type === Lesson::class) {
                $lessonIds[] = $track->trackable_id;
            } elseif ($track->trackable_type === Scenario::class) {
                $scenarioIds[] = $track->trackable_id;
            }
        }

        // جلب بيانات التقدم للدروس بشكل batch
        $lessonProgressMap = [];
        if (!empty($lessonIds)) {
            $lessonProgresses = UserLessonProgress::where('user_id', $userId)
                ->whereIn('lesson_id', $lessonIds)
                ->get()
                ->keyBy('lesson_id');

            foreach ($lessonProgresses as $progress) {
                $lessonProgressMap[$progress->lesson_id] = [
                    'is_completed' => ($progress->is_completed ?? false) 
                        || ($progress->status === 'completed') 
                        || (($progress->progress_percentage ?? 0) >= 100)
                        || ($progress->track_status === 'completed'),
                ];
            }

            // Fallback: التحقق من المحاولات المنتهية
            $finishedAttempts = UserLessonAttempt::where('user_id', $userId)
                ->whereIn('lesson_id', $lessonIds)
                ->where('status', 'finished')
                ->distinct('lesson_id')
                ->pluck('lesson_id')
                ->toArray();

            foreach ($finishedAttempts as $lessonId) {
                if (!isset($lessonProgressMap[$lessonId])) {
                    $lessonProgressMap[$lessonId] = ['is_completed' => true];
                }
            }
        }

        // جلب بيانات التقدم للسيناريوهات بشكل batch
        $scenarioProgressMap = [];
        if (!empty($scenarioIds)) {
            // جلب أحدث محاولة لكل سيناريو
            $scenarioAttempts = UserScenarioAttempt::where('user_id', $userId)
                ->whereIn('scenario_id', $scenarioIds)
                ->selectRaw('scenario_id, MAX(started_at) as max_started_at')
                ->groupBy('scenario_id')
                ->get();

            $scenarioAttemptIds = [];
            foreach ($scenarioAttempts as $attempt) {
                $latestAttempt = UserScenarioAttempt::where('user_id', $userId)
                    ->where('scenario_id', $attempt->scenario_id)
                    ->where('started_at', $attempt->max_started_at)
                    ->first();
                
                if ($latestAttempt) {
                    $scenarioAttemptIds[] = $latestAttempt->id;
                }
            }

            if (!empty($scenarioAttemptIds)) {
                $latestScenarioAttempts = UserScenarioAttempt::whereIn('id', $scenarioAttemptIds)
                    ->get()
                    ->keyBy('scenario_id');

                foreach ($latestScenarioAttempts as $attempt) {
                    $scenarioProgressMap[$attempt->scenario_id] = [
                        'is_completed' => ($attempt->is_completed ?? false)
                            || (($attempt->progress_percentage ?? 0) >= 100)
                            || ($attempt->status === 'finished')
                            || ($attempt->track_status === 'completed'),
                    ];
                }
            }

            // Fallback: التحقق من المحاولات المنتهية
            $finishedScenarioAttempts = UserScenarioAttempt::where('user_id', $userId)
                ->whereIn('scenario_id', $scenarioIds)
                ->where('status', 'finished')
                ->distinct('scenario_id')
                ->pluck('scenario_id')
                ->toArray();

            foreach ($finishedScenarioAttempts as $scenarioId) {
                if (!isset($scenarioProgressMap[$scenarioId])) {
                    $scenarioProgressMap[$scenarioId] = ['is_completed' => true];
                }
            }
        }

        // 6. معالجة كل مستوى
        foreach ($levels as $level) {
            $levelId = $level->id;
            $userLevelProgress = $userLevelProgressMap->get($levelId);

            // التحقق من الإكمال
            $isCompleted = false;
            if ($userLevelProgress && $userLevelProgress->status === 'completed') {
                $isCompleted = true;
            } else {
                // استخدام levelTracks المحملة مسبقاً
                $levelTracks = $allLevelTracks->get($levelId, collect());

                if ($levelTracks->isNotEmpty()) {
                    $allCompleted = true;
                    foreach ($levelTracks as $track) {
                        if (!$track->trackable || !$this->isTrackablePublished($track->trackable)) {
                            continue;
                        }

                        // استخدام البيانات المحملة مسبقاً
                        $trackableId = $track->trackable_id;
                        if ($track->trackable_type === Lesson::class) {
                            $trackCompleted = $lessonProgressMap[$trackableId]['is_completed'] ?? false;
                        } else {
                            $trackCompleted = $scenarioProgressMap[$trackableId]['is_completed'] ?? false;
                        }

                        if (!$trackCompleted) {
                            $allCompleted = false;
                            break;
                        }
                    }
                    $isCompleted = $allCompleted;
                }
            }

            // تحديد access_status
            $accessStatus = 'locked';
            if ($isCompleted) {
                $accessStatus = 'completed';
            } elseif ($level->course_id && isset($allCourseLevels[$level->course_id])) {
                // العثور على المستوى السابق
                $courseLevels = $allCourseLevels[$level->course_id];
                $previousLevel = null;
                foreach ($courseLevels as $courseLevel) {
                    if ($courseLevel->order_index < $level->order_index) {
                        $previousLevel = $courseLevel;
                    } else {
                        break;
                    }
                }

                if (!$previousLevel) {
                    $accessStatus = 'open';
                } else {
                    $previousUserProgress = $userLevelProgressMap->get($previousLevel->id);
                    if ($previousUserProgress && $previousUserProgress->status === 'completed') {
                        $accessStatus = 'open';
                    } else {
                        // استخدام levelTracks المحملة مسبقاً للمستوى السابق
                        $previousLevelTracks = $allCourseLevelTracks->get($previousLevel->id, collect());

                        $previousCompleted = true;
                        if ($previousLevelTracks->isNotEmpty()) {
                            foreach ($previousLevelTracks as $track) {
                                if (!$track->trackable || !$this->isTrackablePublished($track->trackable)) {
                                    continue;
                                }

                                // استخدام البيانات المحملة مسبقاً
                                $trackableId = $track->trackable_id;
                                if ($track->trackable_type === Lesson::class) {
                                    $trackCompleted = $lessonProgressMap[$trackableId]['is_completed'] ?? false;
                                } else {
                                    $trackCompleted = $scenarioProgressMap[$trackableId]['is_completed'] ?? false;
                                }

                                if (!$trackCompleted) {
                                    $previousCompleted = false;
                                    break;
                                }
                            }
                        } else {
                            $previousCompleted = false;
                        }

                        $accessStatus = $previousCompleted ? 'open' : 'locked';
                    }
                }
            } else {
                $accessStatus = 'open';
            }

            // الحصول على الشهادة
            $certificate = $certificatesMap->get($levelId);

            $result[$levelId] = [
                'is_completed' => $isCompleted,
                'access_status' => $accessStatus,
                'certificate' => $certificate,
            ];
        }

        return $result;
    }
}

