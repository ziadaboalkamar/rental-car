<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->after('trial_ends_at');
            $table->timestamp('stripe_onboarded_at')->nullable()->after('stripe_account_id');
            $table->boolean('stripe_details_submitted')->default(false)->after('stripe_onboarded_at');
            $table->boolean('stripe_charges_enabled')->default(false)->after('stripe_details_submitted');
            $table->boolean('stripe_payouts_enabled')->default(false)->after('stripe_charges_enabled');
            $table->string('stripe_currency', 3)->nullable()->after('stripe_payouts_enabled');

            $table->index('stripe_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['stripe_account_id']);
            $table->dropColumn([
                'stripe_account_id',
                'stripe_onboarded_at',
                'stripe_details_submitted',
                'stripe_charges_enabled',
                'stripe_payouts_enabled',
                'stripe_currency',
            ]);
        });
    }
};
