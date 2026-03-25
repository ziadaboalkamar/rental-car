<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('role')->default('additional');
            $table->unsignedInteger('sort_order')->default(0);

            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('nationality')->nullable();
            $table->date('date_of_birth')->nullable();

            $table->string('identity_number')->nullable();
            $table->string('residency_number')->nullable();
            $table->string('license_number')->nullable();
            $table->date('identity_expiry_date')->nullable();
            $table->date('license_expiry_date')->nullable();

            $table->string('extraction_status')->default('not_requested');
            $table->json('extracted_data')->nullable();
            $table->json('raw_output')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'contract_id'], 'contract_drivers_tenant_contract_idx');
            $table->index(['tenant_id', 'client_id'], 'contract_drivers_tenant_client_idx');
            $table->index(['contract_id', 'role', 'sort_order'], 'contract_drivers_contract_role_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_drivers');
    }
};
