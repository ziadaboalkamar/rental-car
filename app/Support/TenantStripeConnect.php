<?php

namespace App\Support;

use App\Models\Tenant;
use Carbon\Carbon;
use Stripe\StripeClient;

class TenantStripeConnect
{
    public function platformSecret(): ?string
    {
        $secret = trim((string) config('cashier.secret', ''));

        return $secret !== '' ? $secret : null;
    }

    public function isConfigured(): bool
    {
        return $this->platformSecret() !== null;
    }

    public function client(): StripeClient
    {
        $secret = $this->platformSecret();

        if (!$secret) {
            throw new \RuntimeException('Stripe is not configured.');
        }

        return new StripeClient($secret);
    }

    public function ensureConnectedAccount(Tenant $tenant): Tenant
    {
        if ($tenant->stripe_account_id) {
            return $tenant;
        }

        $account = $this->client()->accounts->create([
            'type' => 'express',
            'country' => 'US',
            'email' => $tenant->email,
            'business_type' => 'company',
            'business_profile' => [
                'name' => $tenant->name,
                'url' => $this->tenantBaseUrl($tenant),
                'product_description' => 'Car rental services',
            ],
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'tenant_slug' => (string) $tenant->slug,
            ],
        ]);

        $tenant->forceFill([
            'stripe_account_id' => (string) $account->id,
        ])->save();

        return $tenant->refresh();
    }

    public function syncAccountStatus(Tenant $tenant): Tenant
    {
        if (!$tenant->stripe_account_id) {
            return $tenant;
        }

        $account = $this->client()->accounts->retrieve($tenant->stripe_account_id, []);

        $tenant->forceFill([
            'stripe_details_submitted' => (bool) ($account->details_submitted ?? false),
            'stripe_charges_enabled' => (bool) ($account->charges_enabled ?? false),
            'stripe_payouts_enabled' => (bool) ($account->payouts_enabled ?? false),
            'stripe_currency' => strtoupper((string) ($account->default_currency ?? '')) ?: $tenant->stripe_currency,
            'stripe_onboarded_at' => ($account->details_submitted ?? false)
                ? ($tenant->stripe_onboarded_at ?? Carbon::now())
                : null,
        ])->save();

        return $tenant->refresh();
    }

    public function createOnboardingLink(Tenant $tenant, string $returnUrl, string $refreshUrl): string
    {
        $tenant = $this->ensureConnectedAccount($tenant);

        $link = $this->client()->accountLinks->create([
            'account' => $tenant->stripe_account_id,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return (string) $link->url;
    }

    public function createLoginLink(Tenant $tenant): string
    {
        if (!$tenant->stripe_account_id) {
            throw new \RuntimeException('Tenant has no Stripe account connected.');
        }

        $link = $this->client()->accounts->createLoginLink($tenant->stripe_account_id, []);

        return (string) $link->url;
    }

    public function canAcceptCheckout(Tenant $tenant): bool
    {
        return (bool) $tenant->stripe_account_id
            && (bool) $tenant->stripe_charges_enabled
            && (bool) $tenant->stripe_details_submitted;
    }

    public function tenantBaseUrl(Tenant $tenant): string
    {
        $baseHost = (string) parse_url(config('app.url'), PHP_URL_HOST);
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'http';

        return sprintf('%s://%s.%s', $scheme, $tenant->slug, $baseHost);
    }
}
