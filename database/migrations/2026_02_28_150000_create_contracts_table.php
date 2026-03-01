<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();

            $table->string('contract_number');
            $table->string('status')->default('draft');
            $table->date('contract_date')->nullable();

            $table->string('renter_name')->nullable();
            $table->string('renter_id_number')->nullable();
            $table->string('renter_phone')->nullable();

            $table->string('car_details')->nullable();
            $table->string('plate_number')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->decimal('total_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->text('notes')->nullable();

            $table->string('ai_extraction_status')->default('disabled');
            $table->json('ai_extracted_data')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'contract_number'], 'contracts_tenant_contract_no_uniq');
            $table->unique('reservation_id', 'contracts_reservation_id_uniq');
            $table->index(['tenant_id', 'status'], 'contracts_tenant_status_idx');
            $table->index(['tenant_id', 'branch_id'], 'contracts_tenant_branch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};

