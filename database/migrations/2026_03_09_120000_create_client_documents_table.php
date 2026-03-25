<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('extraction_status')->default('not_requested');
            $table->string('extraction_provider')->nullable();
            $table->string('extraction_engine')->nullable();
            $table->longText('raw_text')->nullable();
            $table->json('raw_output')->nullable();
            $table->json('extracted_data')->nullable();
            $table->json('approved_data')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'document_type'], 'client_documents_tenant_user_type_uniq');
            $table->index(['tenant_id', 'document_type'], 'client_documents_tenant_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_documents');
    }
};
