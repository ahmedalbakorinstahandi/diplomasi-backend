<?php

namespace App\Events;

use App\Models\Progress\UserCourse;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCourseCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public UserCourse $userCourse;

    /**
     * Create a new event instance.
     */
    public function __construct(UserCourse $userCourse)
    {
        $this->userCourse = $userCourse;
    }
}
