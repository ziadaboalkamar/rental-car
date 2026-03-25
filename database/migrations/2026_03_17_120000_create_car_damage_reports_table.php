<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_damage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_number');
            $table->string('report_type')->default('before_delivery');
            $table->string('status')->default('draft');
            $table->dateTime('inspected_at')->nullable();
            $table->unsignedInteger('odometer')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'report_number'], 'car_damage_reports_tenant_report_number_uniq');
            $table->index(['tenant_id', 'car_id'], 'car_damage_reports_tenant_car_idx');
            $table->index(['tenant_id', 'branch_id'], 'car_damage_reports_tenant_branch_idx');
            $table->index(['tenant_id', 'contract_id'], 'car_damage_reports_tenant_contract_idx');
            $table->index(['tenant_id', 'report_type'], 'car_damage_reports_tenant_type_idx');
            $table->index(['tenant_id', 'status'], 'car_damage_reports_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_damage_reports');
    }
};
