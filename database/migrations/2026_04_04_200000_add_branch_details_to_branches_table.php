<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('country')->nullable()->after('email');
            $table->string('city')->nullable()->after('country');
            $table->string('street_name')->nullable()->after('city');
            $table->string('street_number')->nullable()->after('street_name');
            $table->string('building_number')->nullable()->after('street_number');
            $table->string('office_number')->nullable()->after('building_number');
            $table->string('post_code')->nullable()->after('office_number');
            $table->string('google_map_url', 1000)->nullable()->after('post_code');
            $table->string('phone_1')->nullable()->after('google_map_url');
            $table->string('phone_2')->nullable()->after('phone_1');
            $table->string('whatsapp')->nullable()->after('phone_2');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'country',
                'city',
                'street_name',
                'street_number',
                'building_number',
                'office_number',
                'post_code',
                'google_map_url',
                'phone_1',
                'phone_2',
                'whatsapp',
            ]);
        });
    }
};
