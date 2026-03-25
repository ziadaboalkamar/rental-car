<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_damage_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('opened_in_contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('opened_in_reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignId('last_report_id')->nullable()->constrained('car_damage_reports')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('zone_code');
            $table->string('view_side')->default('left');
            $table->string('damage_type');
            $table->string('severity')->default('minor');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('marker_x', 8, 2)->nullable();
            $table->decimal('marker_y', 8, 2)->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('open');
            $table->dateTime('first_detected_at')->nullable();
            $table->dateTime('last_detected_at')->nullable();
            $table->dateTime('repaired_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'car_id'], 'car_damage_cases_tenant_car_idx');
            $table->index(['tenant_id', 'branch_id'], 'car_damage_cases_tenant_branch_idx');
            $table->index(['tenant_id', 'status'], 'car_damage_cases_tenant_status_idx');
            $table->index(['tenant_id', 'zone_code'], 'car_damage_cases_tenant_zone_idx');
            $table->index(['tenant_id', 'opened_in_contract_id'], 'car_damage_cases_tenant_contract_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_damage_cases');
    }
};
