<?php

namespace App\Models;

use App\Core\TenantContext;
use Laratrust\Models\Role as RoleModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Permission;
use App\Traits\BelongsToTenant;
use App\Enums\UserRole;

class Role extends RoleModel
{
    use BelongsToTenant;

    public $guarded = [];

    protected $fillable = ['name', 'display_name', 'description', 'tenant_id'];

    public function permissions(): BelongsToMany
    {
        $relation = $this->belongsToMany(Permission::class, 'permission_role')
            ->withoutGlobalScope('tenant');

        // Super Admin can inspect all permissions.
        if (auth()->check() && auth()->user()->role === UserRole::SUPER_ADMIN) {
            return $relation;
        }

        $tenantId = TenantContext::id() ?? $this->tenant_id ?? auth()->user()?->tenant_id;

        // Tenant roles may use global tenant-* permissions (tenant_id NULL) and optional tenant-specific permissions.
        if ($tenantId) {
            $relation->where(function ($query) use ($tenantId) {
                $query->whereNull('permissions.tenant_id')
                    ->orWhere('permissions.tenant_id', $tenantId);
            });
        } else {
            $relation->whereNull('permissions.tenant_id');
        }

        return $relation;
    }
}
