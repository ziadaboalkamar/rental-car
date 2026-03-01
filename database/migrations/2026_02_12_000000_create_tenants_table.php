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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Company/Business name
            $table->string('slug')->unique(); // URL-friendly identifier
            $table->string('domain')->nullable()->unique(); // Optional custom domain
            $table->string('email')->nullable(); // Tenant contact email
            $table->string('phone')->nullable(); // Tenant contact phone
            $table->string('plan')->default('basic'); // Subscription plan
            $table->boolean('is_active')->default(true); // Can be suspended
            $table->json('settings')->nullable(); // Tenant-specific settings
            $table->timestamp('trial_ends_at')->nullable(); // Trial period
            $table->timestamps();
            $table->softDeletes(); // Soft delete for safety
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
