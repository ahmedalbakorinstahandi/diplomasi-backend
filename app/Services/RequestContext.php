<?php

namespace App\Services;

class RequestContext
{
    public const CONTEXT_APP = 'app';
    public const CONTEXT_DASHBOARD = 'dashboard';

    public static function get(): string
    {
        $ctx = app()->bound('request.context') ? app('request.context') : self::CONTEXT_APP;

        return in_array($ctx, [self::CONTEXT_APP, self::CONTEXT_DASHBOARD], true)
            ? $ctx
            : self::CONTEXT_APP;
    }

    public static function isApp(): bool
    {
        return self::get() === self::CONTEXT_APP;
    }

    public static function isDashboard(): bool
    {
        return self::get() === self::CONTEXT_DASHBOARD;
    }
}
