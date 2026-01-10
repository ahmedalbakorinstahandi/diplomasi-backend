<?php

namespace App\Events;

use App\Models\Progress\UserLevelProgress;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserLevelCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public UserLevelProgress $userLevelProgress;

    /**
     * Create a new event instance.
     */
    public function __construct(UserLevelProgress $userLevelProgress)
    {
        $this->userLevelProgress = $userLevelProgress;
    }
}
