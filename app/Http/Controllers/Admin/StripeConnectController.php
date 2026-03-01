<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\TenantStripeConnect;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class StripeConnectController extends Controller
{
    public function __construct(private readonly TenantStripeConnect $stripeConnect)
    {
    }

    public function edit(): Response|RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        if ($tenant->stripe_account_id && $this->stripeConnect->isConfigured()) {
            try {
                $tenant = $this->stripeConnect->syncAccountStatus($tenant);
            } catch (Throwable) {
                // Keep page usable even if Stripe API is temporarily unavailable.
            }
        }

        return Inertia::render('Admin/Settings/StripeConnect', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'stripe_account_id' => $tenant->stripe_account_id,
                'stripe_onboarded_at' => optional($tenant->stripe_onboarded_at)?->toDateTimeString(),
                'stripe_details_submitted' => (bool) $tenant->stripe_details_submitted,
                'stripe_charges_enabled' => (bool) $tenant->stripe_charges_enabled,
                'stripe_payouts_enabled' => (bool) $tenant->stripe_payouts_enabled,
                'stripe_currency' => $tenant->stripe_currency,
            ],
            'stripe' => [
                'platform_configured' => $this->stripeConnect->isConfigured(),
                'can_accept_checkout' => $this->stripeConnect->canAcceptCheckout($tenant),
            ],
            'actions' => [
                'connect' => route('admin.settings.stripe-connect.connect', ['subdomain' => $tenant->slug]),
                'refresh' => route('admin.settings.stripe-connect.refresh', ['subdomain' => $tenant->slug]),
                'login_link' => route('admin.settings.stripe-connect.login-link', ['subdomain' => $tenant->slug]),
            ],
        ]);
    }

    public function connect(): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        if (!$this->stripeConnect->isConfigured()) {
            return back()->with('error', 'Platform Stripe is not configured.');
        }

        try {
            $url = $this->stripeConnect->createOnboardingLink(
                $tenant,
                route('admin.settings.stripe-connect.return', ['subdomain' => $tenant->slug]),
                route('admin.settings.stripe-connect.refresh', ['subdomain' => $tenant->slug]),
            );

            return Inertia::location($url);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Stripe Connect error: '.$e->getMessage());
        }
    }

    public function refresh(): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        if (!$this->stripeConnect->isConfigured()) {
            return redirect()
                ->route('admin.settings.stripe-connect.edit', ['subdomain' => $tenant->slug])
                ->with('error', 'Platform Stripe is not configured.');
        }

        try {
            $url = $this->stripeConnect->createOnboardingLink(
                $tenant,
                route('admin.settings.stripe-connect.return', ['subdomain' => $tenant->slug]),
                route('admin.settings.stripe-connect.refresh', ['subdomain' => $tenant->slug]),
            );

            return redirect()->away($url);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.settings.stripe-connect.edit', ['subdomain' => $tenant->slug])
                ->with('error', 'Stripe Connect error: '.$e->getMessage());
        }
    }

    public function returned(): RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        try {
            if ($this->stripeConnect->isConfigured() && $tenant->stripe_account_id) {
                $tenant = $this->stripeConnect->syncAccountStatus($tenant);
            }
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.settings.stripe-connect.edit', ['subdomain' => $tenant->slug])
                ->with('error', 'Stripe account connected, but status refresh failed. Please click refresh.');
        }

        $message = $tenant->stripe_charges_enabled
            ? 'Stripe account connected successfully.'
            : 'Stripe onboarding is not complete yet. Please finish the required steps in Stripe.';

        return redirect()
            ->route('admin.settings.stripe-connect.edit', ['subdomain' => $tenant->slug])
            ->with('success', $message);
    }

    public function loginLink(): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        if (!$tenant->stripe_account_id) {
            return back()->with('error', 'Connect Stripe first.');
        }

        try {
            $url = $this->stripeConnect->createLoginLink($tenant);

            return Inertia::location($url);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Could not open Stripe dashboard.');
        }
    }
}
