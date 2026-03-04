<?php

namespace App\Listeners;

use App\Events\UserLevelCompleted;
use App\Http\Notifications\LearningNotification;

class SendLevelCompletedNotification
{
    public function handle(UserLevelCompleted $event): void
    {
        $progress = $event->userLevelProgress->loadMissing(['level']);

        LearningNotification::levelCompleted(
            userId: (int) $progress->user_id,
            levelId: (int) $progress->level_id,
            levelTitle: (string) ($progress->level?->title ?? '')
        );
    }
}
