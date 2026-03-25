<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_driver_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_driver_id')->constrained()->cascadeOnDelete();

            $table->string('document_type');
            $table->string('side')->default('single');

            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();

            $table->string('ocr_status')->default('pending');
            $table->string('ocr_provider')->nullable();
            $table->json('raw_ocr_json')->nullable();
            $table->json('normalized_json')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'contract_driver_id'], 'contract_driver_docs_tenant_driver_idx');
            $table->index(['contract_driver_id', 'document_type', 'side'], 'contract_driver_docs_driver_type_side_idx');
            $table->index(['tenant_id', 'ocr_status'], 'contract_driver_docs_tenant_ocr_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_driver_documents');
    }
};
