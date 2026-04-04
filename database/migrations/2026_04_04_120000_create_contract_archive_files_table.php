<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_archive_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_driver_id')->nullable()->constrained('contract_drivers')->nullOnDelete();
            $table->string('document_type', 100)->nullable();
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'contract_driver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_archive_files');
    }
};
