<?php

namespace App\Http\Middleware;

use App\Models\Users\User;
use App\Services\MessageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        if ($user->isGuest()) {
            MessageService::abort(403, 'messages.permission.error');
        }

        return $next($request);
    }
}
