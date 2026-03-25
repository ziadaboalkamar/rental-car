<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_damage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_damage_report_id')->constrained('car_damage_reports')->cascadeOnDelete();
            $table->string('zone_code');
            $table->string('view_side')->default('left');
            $table->string('damage_type');
            $table->string('severity')->default('minor');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('marker_x', 8, 2)->nullable();
            $table->decimal('marker_y', 8, 2)->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'car_damage_report_id'], 'car_damage_items_tenant_report_idx');
            $table->index(['tenant_id', 'zone_code'], 'car_damage_items_tenant_zone_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_damage_items');
    }
};
