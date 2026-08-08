<?php

namespace App\Http\Controllers\AiNegotiator;

use App\Http\Controllers\Controller;
use App\Http\Notifications\AiNegotiatorNotification;
use App\Http\Permissions\AiNegotiator\AiNegotiatorSettingsPermission;
use App\Http\Requests\AiNegotiator\NotifyAiNegotiatorSettingsChangeRequest;
use App\Models\Users\User;
use App\Services\ResponseService;

class AiNegotiatorSettingsNotificationController extends Controller
{
    public function notifySubscribers(NotifyAiNegotiatorSettingsChangeRequest $request)
    {
        AiNegotiatorSettingsPermission::canManage();

        $graceMinutes = (int) config('services.billing.renewal_grace_period_minutes', 15);
        $graceCutoff = now()->subMinutes($graceMinutes);

        $subscriberIds = User::query()
            ->whereHas('subscriptions', function ($query) use ($graceCutoff) {
                $query->whereIn('status', ['active', 'past_due'])
                    ->where(function ($q) use ($graceCutoff) {
                        $q->where('end_date', '>=', now())
                            ->orWhere(function ($inner) use ($graceCutoff) {
                                $inner->where('auto_renew', true)
                                    ->where('end_date', '>=', $graceCutoff);
                            });
                    });
            })
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $notified = AiNegotiatorNotification::settingsChanged(
            $subscriberIds,
            $request->validated('message')
        );

        return ResponseService::response([
            'success' => true,
            'data' => [
                'notified_count' => $notified,
            ],
            'message' => 'messages.ai_negotiator.subscribers_notified',
            'status' => 200,
        ]);
    }
}
