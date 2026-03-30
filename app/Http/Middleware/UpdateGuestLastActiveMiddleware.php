<?php

namespace App\Http\Middleware;

use App\Models\Users\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UpdateGuestLastActiveMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = User::auth();
        if (!$user || !$user->isGuest()) {
            return $next($request);
        }

        $throttleKey = 'guest_last_active_touch_' . $user->id;
        if (cache()->add($throttleKey, true, 60)) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'guest_last_active_at' => now(),
                ]);
        }

        return $next($request);
    }
}
