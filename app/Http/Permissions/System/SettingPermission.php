<?php

namespace App\Http\Permissions\System;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;

class SettingPermission
{
    public static function filterIndex($query)
    {
        self::canView();

        // App/guest context: only public settings are visible.
        if (RequestContext::isApp()) {
            $query->where('is_settings', true);
        }

        return $query;
    }

    public static function canView(): void
    {
        // if (RequestContext::isDashboard()) {
        //     AuthorizationService::authorize('setting.view');
        // }
    }

    public static function canShow($setting): void
    {
        self::canView();

        if (RequestContext::isApp() && !$setting->is_settings) {
            MessageService::abort(404, 'messages.setting.not_found');
        }
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('setting.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('setting.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('setting.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdateMany(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('setting.update_many');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}

