<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table) {
            $table->json('enabled_locales')->nullable()->after('tax_percentage');
            $table->json('translations')->nullable()->after('contact_page');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table) {
            $table->dropColumn(['enabled_locales', 'translations']);
        });
    }
};

