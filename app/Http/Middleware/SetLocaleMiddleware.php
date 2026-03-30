<?php

namespace App\Http\Middleware;

use App\Models\System\Setting;
use App\Models\Users\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // $locale = explode(',', $request->header('Accept-Language', 'en'))[0];
        $locale = 'ar';
        if (in_array($locale, ['ar', 'en'])) {
            app()->setLocale($locale);
        }

        // Store authenticated user in cache for this request
        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();

            // Banned user: block all requests except logout (POST) and help center (GET)
            if ($user->status === 'banned') {
                $path = $request->path();
                $allowLogout = $request->isMethod('POST') && (str_contains($path, 'auth/logout') || str_contains($path, 'logout'));
                $allowHelpCenter = str_contains($path, 'help_center') && $request->isMethod('GET');
                if ($allowLogout || $allowHelpCenter) {
                    return $next($request);
                }
                return response()->json([
                    'success' => false,
                    'key' => 'messages.user.is_banned',
                    'message' => __('auth.account_banned'),
                    'data' => null,
                ], 200);
            }

            $token = $request->bearerToken();
            if ($token) {
                $cacheKey = 'request_user_' . $token;

                // Store or update user in cache with TTL refresh
                cache()->put($cacheKey, $user, 300); // 5 minutes - refreshes TTL if exists
            }

            $this->touchUserActivity((int) $user->id, $user);
        }

        // Force update: app version below min_version — فقط عندما الطلب من التطبيق (X-Context: app)
        $path = $request->path();
        $isWebhook = str_contains($path, 'webhooks');
        $context = strtolower((string) $request->header('X-Context', ''));
        $isFromApp = $context === 'app';
        $applyForceUpdate = $isFromApp && !$isWebhook;

        if ($applyForceUpdate) {
            $appVersion = $request->header('X-App-Version', '0.0.0');
            $minVersionSetting = Setting::where('key_name', 'app.min_version')->first();
            $minVersion = $minVersionSetting ? (string) $minVersionSetting->value : null;

            if ($minVersion !== null && version_compare($appVersion, $minVersion, '<')) {
                $playLink = Setting::where('key_name', 'app.google_play_link')->first();
                $appleLink = Setting::where('key_name', 'app.apple_store_link')->first();

                return response()->json([
                    'success' => false,
                    'key' => 'app.force_update',
                    'message' => __('auth.force_update'),
                    'data' => [
                        'store_link_android' => $playLink ? (string) $playLink->value : null,
                        'store_link_ios' => $appleLink ? (string) $appleLink->value : null,
                    ],
                ], 200);
            }
        }

        return $next($request);
    }

    private function touchUserActivity(int $userId, mixed $user): void
    {
        if ($userId <= 0) {
            return;
        }

        $throttleKey = 'user_activity_touch_' . $userId;
        if (!cache()->add($throttleKey, true, 60)) {
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
