<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Users\RoleManagementPermission;
use App\Http\Resources\Users\PermissionResource;
use App\Models\Users\Permission;
use App\Services\FilterService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class PermissionListController extends Controller
{
    public function index(Request $request)
    {
        RoleManagementPermission::canViewPermissions();

        $filters = $request->all();
        $filters['per_page'] = $filters['per_page'] ?? 50;
        $filters['sort_field'] = $filters['sort_field'] ?? 'id';
        $filters['sort_order'] = $filters['sort_order'] ?? 'asc';

        $query = Permission::query();

        $searchFields = ['name', 'description'];
        $numericFields = [];
        $dateFields = ['created_at'];
        $exactMatchFields = ['id', 'name'];
        $inFields = ['name'];

        $permissions = FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );

        return ResponseService::response([
            'success' => true,
            'data' => $permissions,
            'meta' => true,
            'resource' => PermissionResource::class,
            'status' => 200,
        ]);
    }
}

