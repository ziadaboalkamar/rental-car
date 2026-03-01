<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPaymentTransaction extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'plan_id',
        'payment_provider_id',
        'provider_code',
        'billing_cycle',
        'amount',
        'currency',
        'status',
        'provider_checkout_id',
        'provider_transaction_id',
        'provider_reference',
        'return_status',
        'payer_name',
        'payer_email',
        'payer_phone',
        'paid_at',
        'failed_at',
        'cancelled_at',
        'expires_at',
        'failure_reason',
        'provider_response',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'provider_response' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function paymentProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
