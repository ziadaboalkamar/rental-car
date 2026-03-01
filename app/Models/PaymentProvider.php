<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentProvider extends Model
{
    protected $fillable = [
        'code',
        'name',
        'driver',
        'description',
        'is_enabled',
        'is_default',
        'supports_platform_subscriptions',
        'supports_tenant_payments',
        'mode',
        'config',
        'supported_countries',
        'supported_currencies',
        'sort_order',
        'last_tested_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_default' => 'boolean',
        'supports_platform_subscriptions' => 'boolean',
        'supports_tenant_payments' => 'boolean',
        'config' => 'array',
        'supported_countries' => 'array',
        'supported_currencies' => 'array',
        'last_tested_at' => 'datetime',
    ];

    public function subscriptionTransactions(): HasMany
    {
        return $this->hasMany(SubscriptionPaymentTransaction::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForPlatformSubscriptions($query)
    {
        return $query->where('supports_platform_subscriptions', true);
    }

    public function scopeForTenantPayments($query)
    {
        return $query->where('supports_tenant_payments', true);
    }
}
