<?php

namespace App\Http\Middleware;

use App\Models\Users\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            $token = $request->bearerToken();
            if ($token) {
                $cacheKey = 'request_user_' . $token;

                // Store or update user in cache with TTL refresh
                cache()->put($cacheKey, $user, 300); // 5 minutes - refreshes TTL if exists
            }
        }

        // // Update user language if needed
        // $user = User::auth();
        // if ($user && $user->language != $locale) {
        //     $user->language = $locale;
        //     $user->save();
        // }

        $response = $next($request);

        // Don't manually clean up cache - let Redis handle TTL expiration
        // This prevents issues with concurrent requests and ensures data consistency

        return $response;
    }
}
