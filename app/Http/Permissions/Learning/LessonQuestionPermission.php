<?php

namespace App\Http\Permissions\Learning;

use App\Services\AuthorizationService;
use App\Services\MessageService;
use App\Services\RequestContext;
use Illuminate\Database\Eloquent\Builder;

class LessonQuestionPermission
{
    public static function filterIndex(Builder $query)
    {
        self::canView();

        return $query;
    }

    public static function canView(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('lesson_question.view');
        }
    }

    public static function canCreate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('lesson_question.create');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canUpdate(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('lesson_question.update');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canDelete(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('lesson_question.delete');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }

    public static function canReorder(): void
    {
        if (RequestContext::isDashboard()) {
            AuthorizationService::authorize('lesson_question.reorder');
            return;
        }

        MessageService::abort(403, 'messages.permission.error');
    }
}
