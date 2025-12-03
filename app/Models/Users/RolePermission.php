<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RolePermission extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'role_permissions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'role_id',
        'permission_id',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the role that owns the role permission.
     */
    public function role()
    {
        return $this->belongsTo(Role::class)->withTrashed();
    }

    /**
     * Get the permission that owns the role permission.
     */
    public function permission()
    {
        return $this->belongsTo(Permission::class)->withTrashed();
    }
}

