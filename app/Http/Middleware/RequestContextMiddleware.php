<?php

namespace App\Http\Middleware;

use App\Services\MessageService;
use App\Services\RequestContext;
use Closure;
use Illuminate\Http\Request;

class RequestContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $context = strtolower((string) $request->header('X-Context', RequestContext::CONTEXT_APP));

        // Default to app context for safety.
        if ($context === '') {
            $context = RequestContext::CONTEXT_APP;
        }

        if (!in_array($context, [RequestContext::CONTEXT_APP, RequestContext::CONTEXT_DASHBOARD], true)) {
            MessageService::abort(400, 'messages.validation_error');
        }

        app()->instance('request.context', $context);

        return $next($request);
    }
}
