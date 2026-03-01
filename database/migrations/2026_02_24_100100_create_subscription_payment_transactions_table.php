<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_provider_id')->nullable()->constrained('payment_providers')->nullOnDelete();

            $table->string('provider_code', 50); // duplicate for history even if provider row changes
            $table->string('billing_cycle', 20)->nullable(); // monthly, yearly, one_time
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 30)->default('pending'); // pending, paid, failed, cancelled, expired

            $table->string('provider_checkout_id')->nullable(); // checkout session / invoice id / payment url token
            $table->string('provider_transaction_id')->nullable(); // payment intent / transaction id
            $table->string('provider_reference')->nullable(); // optional display/payment id
            $table->string('return_status')->nullable(); // raw provider return status

            $table->string('payer_name')->nullable();
            $table->string('payer_email')->nullable();
            $table->string('payer_phone')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->text('failure_reason')->nullable();
            $table->json('provider_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'spt_status_created_idx');
            $table->index(['tenant_id', 'status'], 'spt_tenant_status_idx');
            $table->index(['user_id', 'status'], 'spt_user_status_idx');
            $table->index(['plan_id', 'billing_cycle'], 'spt_plan_cycle_idx');
            $table->index(['provider_code', 'status'], 'spt_provider_status_idx');
            $table->index('provider_checkout_id', 'spt_checkout_id_idx');
            $table->index('provider_transaction_id', 'spt_txn_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payment_transactions');
    }
};
