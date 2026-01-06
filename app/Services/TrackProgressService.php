<?php

namespace App\Services;

use App\Models\Learning\Lesson;
use App\Models\Learning\LevelTrack;
use App\Models\Progress\UserLessonAttempt;
use App\Models\Progress\UserLessonQuestionAnswer;
use App\Models\Scenarios\Scenario;
use App\Models\Scenarios\UserScenarioAttempt;

class TrackProgressService
{
    /**
     * Get track status: locked, open, or completed
     *
     * @param LevelTrack $levelTrack
     * @param int $userId
     * @return string
     */
    public function getTrackStatus(LevelTrack $levelTrack, int $userId): string
    {
        // Check if previous track is completed
        if (!$this->canAccessTrack($levelTrack, $userId)) {
            return 'locked';
        }

        // Check if current track is completed
        if ($this->isTrackCompleted($levelTrack->trackable, $userId)) {
            return 'completed';
        }

        return 'open';
    }

    /**
     * Check if user can access a track (previous track is completed)
     *
     * @param LevelTrack $levelTrack
     * @param int $userId
     * @return bool
     */
    public function canAccessTrack(LevelTrack $levelTrack, int $userId): bool
    {
        // First track in level is always accessible
        $firstTrack = LevelTrack::where('level_id', $levelTrack->level_id)
            ->where('order_index', '<', $levelTrack->order_index)
            ->orderBy('order_index', 'desc')
            ->first();

        if (!$firstTrack) {
            return true; // This is the first track
        }

        // Check if previous track is completed
        return $this->isTrackCompleted($firstTrack->trackable, $userId);
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
     * Check if lesson is completed (first attempt is finished)
     *
     * @param Lesson $lesson
     * @param int $userId
     * @return bool
     */
    private function isLessonCompleted(Lesson $lesson, int $userId): bool
    {
        $firstFinishedAttempt = UserLessonAttempt::where('user_id', $userId)
            ->where('lesson_id', $lesson->id)
            ->where('status', 'finished')
            ->orderBy('finished_at', 'asc')
            ->first();

        return $firstFinishedAttempt !== null;
    }

    /**
     * Check if scenario is completed (first attempt is finished)
     *
     * @param Scenario $scenario
     * @param int $userId
     * @return bool
     */
    private function isScenarioCompleted(Scenario $scenario, int $userId): bool
    {
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
     * 30% for video watched + 70% for answered questions
     *
     * @param Lesson $lesson
     * @param int $userId
     * @return float
     */
    private function getLessonProgressPercentage(Lesson $lesson, int $userId): float
    {
        // Get the current or latest attempt
        $attempt = UserLessonAttempt::where('user_id', $userId)
            ->where('lesson_id', $lesson->id)
            ->orderBy('started_at', 'desc')
            ->first();

        if (!$attempt) {
            return 0;
        }

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
            return 100;
        }

        return min(round($progress, 2), 100);
    }

    /**
     * Get scenario progress percentage
     * 30% for description read + 70% when finished
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

        if (!$attempt) {
            return 0;
        }

        $progress = 0;

        // 30% for description read
        if ($attempt->description_read) {
            $progress += 30;
        }

        // 70% when finished
        if ($attempt->status === 'finished') {
            $progress += 70;
        }

        return min(round($progress, 2), 100);
    }

    /**
     * Get previous track for a given level track
     *
     * @param LevelTrack $levelTrack
     * @return LevelTrack|null
     */
    public function getPreviousTrack(LevelTrack $levelTrack): ?LevelTrack
    {
        return LevelTrack::where('level_id', $levelTrack->level_id)
            ->where('order_index', '<', $levelTrack->order_index)
            ->orderBy('order_index', 'desc')
            ->first();
    }
}

