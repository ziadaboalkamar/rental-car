<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table) {
            $table->json('about')->nullable()->after('hero');
            $table->json('contact_page')->nullable()->after('contact');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_site_settings', function (Blueprint $table) {
            $table->dropColumn(['about', 'contact_page']);
        });
    }
};

