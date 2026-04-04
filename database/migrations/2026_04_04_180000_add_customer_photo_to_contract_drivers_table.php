<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_drivers', function (Blueprint $table) {
            $table->string('customer_photo_path')->nullable()->after('license_expiry_date');
            $table->string('customer_photo_name')->nullable()->after('customer_photo_path');
            $table->string('customer_photo_mime_type')->nullable()->after('customer_photo_name');
        });
    }

    public function down(): void
    {
        Schema::table('contract_drivers', function (Blueprint $table) {
            $table->dropColumn(['customer_photo_path', 'customer_photo_name', 'customer_photo_mime_type']);
        });
    }
};
