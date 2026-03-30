<?php

namespace App\Http\Middleware;

use App\Models\Users\User;
use App\Services\MessageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        if ($user->account_state !== 'registered_verified') {
            MessageService::abort(403, 'messages.permission.error');
        }

        return $next($request);
    }
}
