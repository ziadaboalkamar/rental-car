<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('channel', 32)->default('customer')->after('status');
            $table->index('channel');
        });

        DB::table('tickets')
            ->whereNull('user_id')
            ->update(['channel' => 'guest']);

        DB::table('tickets')
            ->whereNotNull('user_id')
            ->where('channel', 'customer')
            ->update(['channel' => 'customer']);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['channel']);
            $table->dropColumn('channel');
        });
    }
};

