<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            
            // Drop the old unique index on name
            $table->dropUnique(['name']);
            
            // Add new composite unique index
            $table->unique(['name', 'tenant_id']);
            
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            
            // Drop unique if exists (usually permissions are global but for consistency)
            // Laratrust setup makes 'name' unique.
            $table->dropUnique(['name']);
            $table->unique(['name', 'tenant_id']);

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique(['name', 'tenant_id']);
            $table->unique('name');
            $table->dropColumn('tenant_id');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique(['name', 'tenant_id']);
            $table->unique('name');
            $table->dropColumn('tenant_id');
        });
    }
};
