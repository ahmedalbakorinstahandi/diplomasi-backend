<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Users\UserPermission;
use App\Http\Requests\Users\CreateUserRequest;
use App\Http\Requests\Users\SyncUserRolesRequest;
use App\Http\Requests\Users\UpdateProfileRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\System\CertificateResource;
use App\Http\Resources\Users\UserResource;
use App\Http\Services\System\CertificateService;
use App\Http\Services\Users\UserMeAppPayloadComposer;
use App\Http\Services\Users\UserService;
use App\Models\Users\User;
use App\Services\FirebaseService;
use App\Services\MessageService;
use App\Services\RequestContext;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request)
    {
        UserPermission::canView();

        $users = $this->userService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'data' => $users,
            'meta' => true,
            'resource' => UserResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        UserPermission::canView();

        $user = $this->userService->show($id);
        UserPermission::canShow($user);

        return ResponseService::response([
            'success' => true,
            'data' => $user,
            'resource' => UserResource::class,
            'status' => 200,
        ]);
    }

    /**
     * شهادات المستخدم (لوحة التحكم).
     */
    public function certificates(Request $request, int $id, CertificateService $certificateService)
    {
        UserPermission::canView();

        $user = $this->userService->show($id);
        UserPermission::canShow($user);

        $certificates = $certificateService->getUserCertificates($id, $request->all());

        return ResponseService::response([
            'success' => true,
            'data' => $certificates,
            'meta' => true,
            'resource' => CertificateResource::class,
            'status' => 200,
        ]);
    }

    public function create(CreateUserRequest $request)
    {
        UserPermission::canCreate();

        $user = $this->userService->create($request->validated());

        return ResponseService::response([
            'success' => true,
            'data' => $user,
            'message' => 'messages.user.created',
            'status' => 201,
            'resource' => UserResource::class,
        ]);
    }

    public function update(UpdateUserRequest $request, int $id)
    {
        $user = $this->userService->show($id);
        UserPermission::canUpdate($user);

        $user = $this->userService->update($request->validated(), $user);

        return ResponseService::response([
            'success' => true,
            'data' => $user,
            'message' => 'messages.user.updated',
            'status' => 200,
            'resource' => UserResource::class,
        ]);
    }

    public function delete(int $id)
    {
        UserPermission::canDelete();

        $user = $this->userService->show($id);

        $this->userService->delete($user);

        return ResponseService::response([
            'success' => true,
            'message' => 'messages.user.deleted',
            'status' => 200,
        ]);
    }

    /**
     * Lightweight ping when the app returns to foreground (mobile).
     * Bypasses the 60s activity throttle so last_opened_app_at stays accurate for re-engagement rules.
     */
    public function heartbeat()
    {
        $user = User::auth();
        if (! $user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $cacheKey = 'user_heartbeat_'.$user->id;
        if (! cache()->add($cacheKey, true, 5)) {
            return ResponseService::response([
                'success' => true,
                'data' => ['ok' => true],
                'status' => 200,
            ]);
        }

        $now = now();
        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'last_activity_at' => $now,
                'last_opened_app_at' => $now,
                'guest_last_active_at' => $user->is_guest ? $now : $user->guest_last_active_at,
                'is_active' => true,
                'inactive_since_at' => null,
                'updated_at' => $now,
            ]);

        return ResponseService::response([
            'success' => true,
            'data' => ['ok' => true],
            'status' => 200,
        ]);
    }

    public function getProfile(Request $request, UserMeAppPayloadComposer $userMeAppPayloadComposer)
    {
        // UserPermission::canView();

        $user = $this->userService->getProfile();
        // UserPermission::canShow($user);

        $params = [
            'success' => true,
            'data' => $user,
            'resource' => UserResource::class,
            'status' => 200,
        ];

        if (RequestContext::isApp()) {
            $params = array_merge($params, $userMeAppPayloadComposer->buildForAppRequest($request));
        }

        return ResponseService::response($params);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $currentUser = $this->userService->getProfile();
        UserPermission::canUpdate($currentUser);

        $validated = $request->validated();
        $deviceToken = trim((string) ($validated['device_token'] ?? ''));
        unset($validated['device_token']);

        $user = $this->userService->updateProfile($validated);

        if ($deviceToken !== '' && ! $user->isGuest()) {
            FirebaseService::subscribeToAllTopic($deviceToken, $user->fresh());
        }

        return ResponseService::response([
            'success' => true,
            'data' => $user,
            'message' => 'messages.profile.updated',
            'status' => 200,
            'resource' => UserResource::class,
        ]);
    }

    public function syncRoles(SyncUserRolesRequest $request, int $id)
    {
        UserPermission::canUpdate();

        $user = $this->userService->show($id);
        $user = $this->userService->syncRoles($user, $request->validated()['role_ids'] ?? []);

        return ResponseService::response([
            'success' => true,
            'data' => $user,
            'message' => 'messages.user.roles_synced',
            'status' => 200,
            'resource' => UserResource::class,
        ]);
    }
}

