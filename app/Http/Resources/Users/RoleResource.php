<?php

namespace App\Http\Resources\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'users' => UserResource::collection($this->whenLoaded('users')),
            'user_roles' => UserRoleResource::collection($this->whenLoaded('userRoles')),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'role_permissions' => RolePermissionResource::collection($this->whenLoaded('rolePermissions')),
        ];
    }
}

