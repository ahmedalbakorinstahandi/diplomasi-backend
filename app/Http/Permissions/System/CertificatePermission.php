<?php

namespace App\Http\Permissions\System;

use App\Models\System\Certificate;
use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;
use App\Models\Users\User;

class CertificatePermission
{
    public static function filterIndex($query)
    {
        self::canView();

        // App context: only own certificates
        if (RequestContext::isApp()) {
            $user = User::auth();
            if (!$user) {
                MessageService::abort(401, 'messages.unauthorized');
            }

            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('certificate.view');
            return;
        }

        // App context: must be authenticated.
        $user = User::auth();
        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        if (!$user->canIssueCertificate()) {
            MessageService::abort(403, 'messages.permission.error');
        }
    }

    public static function canShow(Certificate $certificate): void
    {
        self::canView();

        if (RequestContext::isApp()) {
            $user = User::auth();
            if ($certificate->user_id !== $user->id) {
                MessageService::abort(403, 'messages.permission.error');
            }
        }
    }

    public static function canIssue(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('certificate.issue');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDownload(Certificate $certificate): void
    {
        self::canView();

        if (RequestContext::isApp()) {
            $user = User::auth();
            if ($certificate->user_id !== $user->id) {
                MessageService::abort(403, 'messages.permission.error');
            }
        }
    }

    public static function canRevoke(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('certificate.revoke');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}
