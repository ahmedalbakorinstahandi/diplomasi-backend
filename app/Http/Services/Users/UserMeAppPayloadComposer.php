<?php

namespace App\Http\Services\Users;

use App\Http\Resources\Billing\SubscriptionResource;
use App\Http\Services\Billing\SubscriptionManagementService;
use App\Http\Services\System\AppUpdateSuggestService;
use App\Models\Learning\Course;
use App\Models\Learning\Level;
use App\Models\Users\User;
use Illuminate\Http\Request;

/**
 * Optional and app-only metadata merged into GET /user/me (X-Context: app).
 */
class UserMeAppPayloadComposer
{
    public function __construct(
        protected SubscriptionManagementService $subscriptionManagementService,
        protected AppUpdateSuggestService $appUpdateSuggestService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildForAppRequest(Request $request): array
    {
        $out = [
            'courses_mode' => $this->buildCoursesMode(),
        ];

        if ($this->queryFlagIsTrue($request, 'include_app_update_check')) {
            $appVersion = (string) $request->header('X-App-Version', '0.0.0');
            $out['app_update_check'] = $this->appUpdateSuggestService->buildForClientVersion($appVersion);
        }

        if ($this->queryFlagIsTrue($request, 'include_subscription')) {
            $out['billing_subscription'] = $this->buildBillingSubscriptionPayload($request);
        }

        return $out;
    }

    /**
     * @return array{
     *     total_published_courses: int,
     *     has_single_course: bool,
     *     single_course_id?: int,
     *     single_course_first_level_id?: int|null
     * }
     */
    public function buildCoursesMode(): array
    {
        $publishedCourseIds = Course::query()
            ->where('is_published', true)
            ->orderBy('order_index')
            ->orderBy('id')
            ->pluck('id');

        $count = $publishedCourseIds->count();

        $payload = [
            'total_published_courses' => $count,
            'has_single_course' => $count === 1,
        ];

        if ($count !== 1) {
            return $payload;
        }

        $courseId = (int) $publishedCourseIds->first();
        $payload['single_course_id'] = $courseId;

        $firstLevelId = Level::query()
            ->where('course_id', $courseId)
            ->where('is_published', true)
            ->orderBy('order_index')
            ->orderBy('id')
            ->value('id');

        $payload['single_course_first_level_id'] = $firstLevelId !== null ? (int) $firstLevelId : null;

        return $payload;
    }

    protected function buildBillingSubscriptionPayload(Request $request): ?array
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return null;
        }

        $subscription = $this->subscriptionManagementService->currentForUser((int) $user->id);

        if ($subscription === null) {
            return null;
        }

        $subscription->loadMissing(['plan']);

        return (new SubscriptionResource($subscription))->toArray($request);
    }

    protected function queryFlagIsTrue(Request $request, string $key): bool
    {
        if (! $request->query->has($key)) {
            return false;
        }

        $v = $request->query($key);

        if ($v === true) {
            return true;
        }

        $s = strtolower(trim((string) $v));

        return in_array($s, ['1', 'true', 'yes', 'on'], true);
    }
}
