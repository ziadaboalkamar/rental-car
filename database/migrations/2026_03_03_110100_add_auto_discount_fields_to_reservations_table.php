<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('auto_discount_id')->nullable()->after('coupon_code')->constrained('car_discounts')->nullOnDelete();
            $table->decimal('auto_discount_amount', 10, 2)->default(0)->after('auto_discount_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['auto_discount_id']);
            $table->dropColumn(['auto_discount_id', 'auto_discount_amount']);
        });
    }
};

