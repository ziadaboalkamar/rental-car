<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_visits', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 255)->nullable()->index();
            $table->string('landing_path', 255)->default('/')->index();
            $table->string('referrer_url', 2000)->nullable();
            $table->string('referrer_host', 255)->nullable()->index();
            $table->string('referrer_path', 1000)->nullable();
            $table->string('utm_source', 255)->nullable()->index();
            $table->string('utm_medium', 255)->nullable();
            $table->string('utm_campaign', 255)->nullable();
            $table->string('utm_content', 255)->nullable();
            $table->string('utm_term', 255)->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->timestamp('visited_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_visits');
    }
};
