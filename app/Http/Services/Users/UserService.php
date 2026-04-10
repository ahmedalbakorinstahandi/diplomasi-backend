<?php

namespace App\Http\Services\Users;

use App\Http\Permissions\Users\UserPermission;
use App\Http\Notifications\AccountNotification;
use App\Models\Users\Role;
use App\Models\Users\User;
use App\Models\Users\UserRole;
use App\Services\FilterService;
use App\Services\MessageService;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function index($filters = [])
    {
        $query = User::query()->with(['roles']);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'id';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = [['first_name', 'last_name'], 'email', 'phone'];
        $numericFields = [];
        $dateFields = ['created_at'];
        $exactMatchFields = ['id', 'status'];
        $inFields = ['status'];


        $query->where('is_guest', false);


        $query = UserPermission::filterIndex($query);

        $data = FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );

        return $data;
    }

    public function show(int $id)
    {
        $user = User::where('id', $id)->first();

        if (!$user) {
            MessageService::abort(404, 'messages.user.not_found');
        }

        $user->load(['roles', 'userRoles']);

        return $user;
    }

    public function create($data)
    {

        $user = User::where('email', $data['email'])->first();
        if ($user) {
            MessageService::abort(400, 'messages.user.email_already_exists');
        }


        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user = User::create($data);

        return $this->show($user->id);
    }

    public function update($data, $user)
    {
        $oldStatus = (string) $user->status;

        if (isset($data['email'])) {
            $existingUser = User::where('email', $data['email'])->where('id', '!=', $user->id)->first();
            if ($existingUser) {
                MessageService::abort(400, 'messages.user.email_already_exists');
            }
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        if (($data['status'] ?? null) === 'banned' && $oldStatus !== 'banned') {
            AccountNotification::banned((int) $user->id);
        }

        return $this->show($user->id);
    }

    public function delete($user)
    {
        $user->userRoles()->delete();
        $user->tokens()->delete();
        $user->delete();
    }

    public function getProfile()
    {
        $user = User::auth();

        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $user->load(['roles', 'userRoles']);

        return $user;
    }

    public function updateProfile($data)
    {
        $user = User::auth();

        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        if (isset($data['password'])) {
            if (isset($data['current_password']) && !Hash::check($data['current_password'], $user->password)) {
                MessageService::abort(400, 'auth.password_incorrect');
            }
            $data['password'] = Hash::make($data['password']);
            unset($data['current_password']);
        }

        $user->update($data);

        return $user->load(['roles', 'userRoles']);
    }

    /**
     * Sync user roles by role IDs.
     * Uses soft-delete behavior on user_roles pivot.
     */
    public function syncRoles(User $user, array $roleIds): User
    {
        // Validate that all role IDs exist and are not deleted
        $validRoleIds = Role::query()
            ->whereIn('id', $roleIds)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->values()
            ->all();

        if (count($validRoleIds) !== count($roleIds)) {
            MessageService::abort(400, 'messages.role.invalid_ids');
        }

        $currentRoleIds = UserRole::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->pluck('role_id')
            ->values()
            ->all();

        $toAdd = array_values(array_diff($roleIds, $currentRoleIds));
        $toRemove = array_values(array_diff($currentRoleIds, $roleIds));

        foreach ($toAdd as $roleId) {
            UserRole::withTrashed()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                ],
                [
                    'deleted_at' => null,
                ]
            );
        }

        if (!empty($toRemove)) {
            UserRole::query()
                ->where('user_id', $user->id)
                ->whereIn('role_id', $toRemove)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        }

        return $this->show($user->id);
    }
}
