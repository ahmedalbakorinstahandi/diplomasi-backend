<?php

namespace App\Events;

use App\Models\Negotiation\UserNegotiationLevelProgress;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNegotiationLevelCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public UserNegotiationLevelProgress $userNegotiationLevelProgress;

    /**
     * Create a new event instance.
     *
     * Listeners (notifications, etc.) can be attached later in AppServiceProvider.
     * Intentionally not registered yet — no certificate issuance for this module.
     */
    public function __construct(UserNegotiationLevelProgress $userNegotiationLevelProgress)
    {
        $this->userNegotiationLevelProgress = $userNegotiationLevelProgress;
    }
}
