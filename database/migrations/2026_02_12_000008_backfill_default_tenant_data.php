<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tenantId = DB::table('tenants')->where('slug', 'default-tenant')->value('id');

        if (!$tenantId) {
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => 'Default Tenant',
                'slug' => 'default-tenant',
                'email' => 'tenant@example.com',
                'plan' => 'basic',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')
            ->whereNull('tenant_id')
            ->where('role', '!=', 'super_admin')
            ->update(['tenant_id' => $tenantId]);

        foreach (['cars', 'reservations', 'payments', 'tickets', 'messages'] as $table) {
            DB::table($table)
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $tenantId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty to avoid destructive rollback of tenant mappings.
    }
};
