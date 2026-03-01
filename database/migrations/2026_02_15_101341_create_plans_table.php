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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->decimal('monthly_price', 10, 2);
            $table->string('monthly_price_id')->nullable();
            $table->decimal('yearly_price', 10, 2);
            $table->string('yearly_price_id')->nullable();
            $table->decimal('one_time_price', 10, 2)->nullable();
            $table->string('one_time_price_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
