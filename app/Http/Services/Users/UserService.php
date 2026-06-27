<?php

namespace App\Http\Services\Users;

use App\Http\Permissions\Users\UserPermission;
use App\Http\Notifications\AccountNotification;
use App\Models\Users\Role;
use App\Models\Users\User;
use App\Models\Users\UserRole;
use App\Services\FilterService;
use App\Services\MessageService;
use App\Services\RequestContext;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function index($filters = [])
    {
        $query = User::query()->with(['roles']);

        $filters = $this->normalizeFilters($filters);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'id';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = [['first_name', 'last_name'], 'email', 'phone'];
        $numericFields = [];
        $dateFields = ['created_at'];
        $exactMatchFields = ['id', 'status', 'is_guest'];
        $inFields = ['status'];

        if (array_key_exists('is_guest', $filters) && $filters['is_guest'] !== null && $filters['is_guest'] !== '') {
            $query->where('is_guest', filter_var($filters['is_guest'], FILTER_VALIDATE_BOOLEAN));
        } else {
            $query->where('is_guest', false);
        }

        if (isset($filters['is_active'])) {
            $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
            $query->where('status', $isActive ? 'active' : 'inactive');
        }

        if (!empty($filters['role'])) {
            $roleName = $filters['role'];
            $query->whereHas('roles', function ($q) use ($roleName) {
                $q->where('name', $roleName)->whereNull('roles.deleted_at');
            });
        }

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

    protected function normalizeFilters(array $filters): array
    {
        if (!empty($filters['sort_by']) && empty($filters['sort_field'])) {
            $filters['sort_field'] = $filters['sort_by'];
        }

        if (!empty($filters['sort_direction']) && empty($filters['sort_order'])) {
            $filters['sort_order'] = $filters['sort_direction'];
        }

        if (!empty($filters['from_date']) && empty($filters['created_at_from'])) {
            $filters['created_at_from'] = $filters['from_date'];
        }

        if (!empty($filters['to_date']) && empty($filters['created_at_to'])) {
            $filters['created_at_to'] = $filters['to_date'];
        }

        return $filters;
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

        if (RequestContext::isDashboard()) {
            $user->load(['roles.permissions', 'userRoles']);
        }

        return $user;
    }

    public function updateProfile($data)
    {
        $user = User::auth();

        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        if (isset($data['new_password'])) {
            if (isset($data['current_password']) && !Hash::check($data['current_password'], $user->password)) {
                MessageService::abort(400, 'auth.password_incorrect');
            }
            $data['password'] = Hash::make($data['new_password']);
            unset($data['current_password']);
            unset($data['new_password']);
        }

        $user->update($data);

        if (RequestContext::isDashboard()) {
            return $user->load(['roles.permissions', 'userRoles']);
        }

        return $user->fresh();
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
