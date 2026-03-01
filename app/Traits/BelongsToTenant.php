<?php

namespace App\Traits;

use App\Enums\UserRole;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToTenant(): void
    {
        // Automatically set tenant_id when creating a new model
        static::creating(function (Model $model) {
            if (!empty($model->tenant_id)) {
                return;
            }

            $tenantId = \App\Core\TenantContext::id();

            if (empty($tenantId) && auth()->check()) {
                $tenantId = auth()->user()->tenant_id;
            }

            if (!empty($tenantId)) {
                $model->tenant_id = $tenantId;
            }
        });

        // Apply global scope to filter queries by tenant_id
        static::addGlobalScope('tenant', function (Builder $builder) {
            // Priority 1: TenantContext (from subdomain)
            if ($tenantId = \App\Core\TenantContext::id()) {
                $builder->where($builder->getModel()->qualifyColumn('tenant_id'), $tenantId);
                return;
            }

            // Priority 2: Super Admin bypass
            if (auth()->check() && auth()->user()->role === UserRole::SUPER_ADMIN) {
                return;
            }

            // Priority 3: Fallback to auth user's tenant
            if (auth()->check() && !empty(auth()->user()->tenant_id)) {
                $builder->where($builder->getModel()->qualifyColumn('tenant_id'), auth()->user()->tenant_id);
                return;
            }

            // Fail-closed to avoid cross-tenant reads if no context is found
            // Only apply if it's not a super admin check (handled above)
            if (!auth()->check() || auth()->user()->role !== UserRole::SUPER_ADMIN) {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Get the tenant that owns the model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to exclude the global tenant scope.
     * Use this when you need to query across all tenants (e.g., for Super Admin)
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}
