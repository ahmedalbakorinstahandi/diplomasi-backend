<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Users\UserPermission;
use App\Http\Requests\Users\CreateUserRequest;
use App\Http\Requests\Users\SyncUserRolesRequest;
use App\Http\Requests\Users\UpdateProfileRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\Users\UserResource;
use App\Http\Services\Users\UserService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

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

    public function getProfile()
    {
        UserPermission::canView();

        $user = $this->userService->getProfile();
        UserPermission::canShow($user);

        return ResponseService::response([
            'success' => true,
            'data' => $user,
            'resource' => UserResource::class,
            'status' => 200,
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $currentUser = $this->userService->getProfile();
        UserPermission::canUpdate($currentUser);

        $user = $this->userService->updateProfile($request->validated());

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

