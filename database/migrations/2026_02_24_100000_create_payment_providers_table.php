<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // stripe, myfatoorah, tap, paytabs
            $table->string('name');
            $table->string('driver')->nullable(); // internal service driver name
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('supports_platform_subscriptions')->default(false);
            $table->boolean('supports_tenant_payments')->default(false);
            $table->string('mode', 10)->default('test'); // test/live
            $table->json('config')->nullable(); // provider credentials/settings (encrypt later if needed)
            $table->json('supported_countries')->nullable();
            $table->json('supported_currencies')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();

            $table->index(['is_enabled', 'supports_platform_subscriptions'], 'pp_enabled_platform_idx');
            $table->index(['is_enabled', 'supports_tenant_payments'], 'pp_enabled_tenant_idx');
            $table->index(['is_default', 'is_enabled'], 'pp_default_enabled_idx');
            $table->index('sort_order', 'pp_sort_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_providers');
    }
};
