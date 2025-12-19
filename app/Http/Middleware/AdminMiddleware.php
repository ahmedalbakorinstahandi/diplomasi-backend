<?php

namespace App\Http\Middleware;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;
use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Admin routes must be accessed in dashboard context only.
        if (!RequestContext::isDashboard()) {
            MessageService::abort(403, 'messages.permission.error');
        }

        // Dashboard gate: require admin.access (super_admin bypasses in AuthorizationService)
        AuthorizationService::authorize('admin.access');

        return $next($request);
    }
}
