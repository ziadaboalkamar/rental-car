<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('tenants') || !Schema::hasTable('plans')) {
            return;
        }

        $defaultPlanId = DB::table('plans')
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->value('id');

        if (!$defaultPlanId) {
            return;
        }

        DB::table('tenants')
            ->whereNull('plan_id')
            ->update([
                'plan_id' => $defaultPlanId,
                'trial_ends_at' => now()->addMonth()->toDateTimeString(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: destructive rollback is intentionally omitted.
    }
};
