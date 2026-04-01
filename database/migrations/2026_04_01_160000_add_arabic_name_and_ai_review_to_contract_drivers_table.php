<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contract_drivers', function (Blueprint $table) {
            $table->string('full_name_ar')->nullable()->after('full_name');
            $table->boolean('ai_reviewed')->default(false)->after('confidence');
        });
    }

    public function down(): void
    {
        Schema::table('contract_drivers', function (Blueprint $table) {
            $table->dropColumn(['full_name_ar', 'ai_reviewed']);
        });
    }
};
