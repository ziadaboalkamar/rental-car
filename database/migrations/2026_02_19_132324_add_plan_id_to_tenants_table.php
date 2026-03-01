<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('tenants', 'plan_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->foreignId('plan_id')
                    ->nullable()
                    ->after('plan')
                    ->constrained('plans')
                    ->nullOnDelete();
            });
        }

        $plansByName = DB::table('plans')
            ->select('id', 'name')
            ->get()
            ->mapWithKeys(static fn ($plan) => [strtolower(trim((string) $plan->name)) => (int) $plan->id])
            ->all();

        if (empty($plansByName)) {
            return;
        }

        $defaultAccessEndsAt = now()->addMonth()->toDateTimeString();

        DB::table('tenants')
            ->select('id', 'plan', 'plan_id', 'trial_ends_at')
            ->orderBy('id')
            ->chunkById(200, function ($tenants) use ($plansByName, $defaultAccessEndsAt) {
                foreach ($tenants as $tenant) {
                    $updates = [];
                    $resolvedPlanId = $tenant->plan_id ? (int) $tenant->plan_id : null;

                    if ($resolvedPlanId === null) {
                        $legacyPlan = strtolower(trim((string) ($tenant->plan ?? '')));
                        if ($legacyPlan !== '' && isset($plansByName[$legacyPlan])) {
                            $resolvedPlanId = $plansByName[$legacyPlan];
                            $updates['plan_id'] = $resolvedPlanId;
                        }
                    }

                    if ($resolvedPlanId !== null && $tenant->trial_ends_at === null) {
                        $updates['trial_ends_at'] = $defaultAccessEndsAt;
                    }

                    if ($updates !== []) {
                        DB::table('tenants')
                            ->where('id', $tenant->id)
                            ->update($updates);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('tenants', 'plan_id')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });
    }
};
