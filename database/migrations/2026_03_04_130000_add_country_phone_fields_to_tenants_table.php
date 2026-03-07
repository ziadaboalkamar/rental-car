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
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('country_iso2', 2)->nullable()->after('phone');
            $table->string('phone_country_code', 10)->nullable()->after('country_iso2');
            $table->string('phone_national', 30)->nullable()->after('phone_country_code');
            $table->string('phone_e164', 20)->nullable()->after('phone_national');

            $table->index('country_iso2');
            $table->index('phone_e164');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['country_iso2']);
            $table->dropIndex(['phone_e164']);

            $table->dropColumn([
                'country_iso2',
                'phone_country_code',
                'phone_national',
                'phone_e164',
            ]);
        });
    }
};

