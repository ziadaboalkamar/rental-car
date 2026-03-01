<?php

namespace App\Core;

use App\Models\Tenant;

class TenantContext
{
    /**
     * @var Tenant|null
     */
    protected static ?Tenant $tenant = null;

    /**
     * Set the current tenant.
     */
    public static function set(Tenant $tenant): void
    {
        self::$tenant = $tenant;
    }

    /**
     * Get the current tenant.
     */
    public static function get(): ?Tenant
    {
        return self::$tenant;
    }

    /**
     * Get the current tenant ID.
     */
    public static function id(): ?int
    {
        return self::$tenant?->id;
    }

    /**
     * Clear the tenant context.
     */
    public static function clear(): void
    {
        self::$tenant = null;
    }
}
