<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after auth:sanctum so last_opened_app_at always updates on authenticated API calls.
 * (Route middleware order alone is easy to misread; this is the explicit hook.)
 */
class TouchUserActivityMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('sanctum')->user();
        if ($user !== null) {
            $this->touchUserActivity((int) $user->id, $user);
        }

        return $next($request);
    }

    private function touchUserActivity(int $userId, mixed $user): void
    {
        if ($userId <= 0) {
            return;
        }

        $throttleKey = 'user_activity_touch_' . $userId;
        if (! cache()->add($throttleKey, true, 60)) {
            return;
        }

        $now = now();

        DB::table('users')
            ->where('id', $userId)
            ->update([
                'last_activity_at' => $now,
                'last_opened_app_at' => $now,
                'guest_last_active_at' => $user->is_guest ? $now : $user->guest_last_active_at,
                'is_active' => true,
                'inactive_since_at' => null,
                'updated_at' => $now,
            ]);

        if (is_object($user)) {
            $user->last_activity_at = $now;
            $user->last_opened_app_at = $now;
            if ($user->is_guest) {
                $user->guest_last_active_at = $now;
            }
            $user->is_active = true;
            $user->inactive_since_at = null;
        }
    }
}
