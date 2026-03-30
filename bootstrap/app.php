<?php

use App\Services\MessageService;
use App\Http\Middleware\EnsureNotGuest;
use App\Http\Middleware\EnsureVerifiedUser;
use App\Http\Middleware\UpdateGuestLastActiveMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Http\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            UpdateGuestLastActiveMiddleware::class,
        ]);

        $middleware->alias([
            'ensure.not_guest' => EnsureNotGuest::class,
            'ensure.verified' => EnsureVerifiedUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // تعيين Accept: application/json فقط لطلبات API
        if (request()->is('api/*')) {
            request()->headers->set('Accept', 'application/json');
        }

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                MessageService::abort(401, 'You are not logged in');
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                MessageService::abort(404, 'Route not found');
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                MessageService::abort(405, 'Invalid request method');
            }
        });
    })

    ->create();
