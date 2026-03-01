<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\PaymentProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentProvidersController extends Controller
{
    public function edit(): Response
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        $platformProviders = PaymentProvider::query()
            ->whereIn('code', ['stripe', 'myfatoorah'])
            ->where('supports_tenant_payments', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PaymentProvider $provider) => [
                'id' => $provider->id,
                'code' => $provider->code,
                'name' => $provider->name,
                'description' => $provider->description,
                'is_enabled' => (bool) $provider->is_enabled,
                'mode' => (string) $provider->mode,
                'config' => $provider->config ?? [],
                'supported_countries' => $provider->supported_countries ?? [],
                'supported_currencies' => $provider->supported_currencies ?? [],
            ])
            ->values()
            ->all();

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $paymentSettings = is_array($settings['payment_gateways'] ?? null) ? $settings['payment_gateways'] : [];
        $stripeSettings = is_array($paymentSettings['stripe'] ?? null) ? $paymentSettings['stripe'] : [];
        $myfatoorahSettings = is_array($paymentSettings['myfatoorah'] ?? null) ? $paymentSettings['myfatoorah'] : [];

        return Inertia::render('Admin/Settings/PaymentProviders', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'settings' => [
                    'default_provider' => $paymentSettings['default_provider'] ?? null,
                    'stripe' => [
                        'enabled' => (bool) ($stripeSettings['enabled'] ?? false),
                    ],
                    'myfatoorah' => [
                        'enabled' => (bool) ($myfatoorahSettings['enabled'] ?? false),
                        'country' => (string) ($myfatoorahSettings['country'] ?? ''),
                        'api_token' => (string) ($myfatoorahSettings['api_token'] ?? ''),
                        'api_base_url' => (string) ($myfatoorahSettings['api_base_url'] ?? ''),
                        'payment_method_id' => (string) ($myfatoorahSettings['payment_method_id'] ?? ''),
                        'callback_url' => (string) ($myfatoorahSettings['callback_url'] ?? ''),
                        'error_url' => (string) ($myfatoorahSettings['error_url'] ?? ''),
                        'webhook_secret' => (string) ($myfatoorahSettings['webhook_secret'] ?? ''),
                    ],
                ],
                'stripe_connect' => [
                    'stripe_account_id' => $tenant->stripe_account_id,
                    'stripe_charges_enabled' => (bool) $tenant->stripe_charges_enabled,
                    'stripe_payouts_enabled' => (bool) $tenant->stripe_payouts_enabled,
                    'stripe_details_submitted' => (bool) $tenant->stripe_details_submitted,
                    'stripe_currency' => $tenant->stripe_currency,
                ],
            ],
            'platformProviders' => $platformProviders,
            'actions' => [
                'update' => route('admin.settings.payment-providers.update', ['subdomain' => $tenant->slug]),
                'stripe_connect' => route('admin.settings.stripe-connect.edit', ['subdomain' => $tenant->slug]),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();
        abort_unless($tenant, 404);

        if ($request->input('default_provider') === '') {
            $request->merge(['default_provider' => null]);
        }

        $validated = $request->validate([
            'default_provider' => ['nullable', 'string', 'in:stripe,myfatoorah'],
            'stripe.enabled' => ['required', 'boolean'],
            'myfatoorah.enabled' => ['required', 'boolean'],
            'myfatoorah.country' => ['nullable', 'string', 'max:10'],
            'myfatoorah.api_token' => ['nullable', 'string', 'max:2000'],
            'myfatoorah.api_base_url' => ['nullable', 'string', 'max:255'],
            'myfatoorah.payment_method_id' => ['nullable', 'string', 'max:50'],
            'myfatoorah.callback_url' => ['nullable', 'string', 'max:500'],
            'myfatoorah.error_url' => ['nullable', 'string', 'max:500'],
            'myfatoorah.webhook_secret' => ['nullable', 'string', 'max:1000'],
        ]);

        $platformProviders = PaymentProvider::query()
            ->whereIn('code', ['stripe', 'myfatoorah'])
            ->pluck('is_enabled', 'code')
            ->map(fn ($v) => (bool) $v)
            ->all();
        $myFatoorahMode = PaymentProvider::query()
            ->where('code', 'myfatoorah')
            ->value('mode');

        $defaultProvider = $validated['default_provider'] ?? null;
        if ($defaultProvider && empty($platformProviders[$defaultProvider])) {
            return back()->withErrors([
                'default_provider' => 'This provider is disabled by Super Admin.',
            ]);
        }

        if ((bool) data_get($validated, 'myfatoorah.enabled', false)) {
            $apiToken = trim((string) data_get($validated, 'myfatoorah.api_token', ''));
            $paymentMethodId = trim((string) data_get($validated, 'myfatoorah.payment_method_id', ''));

            if ($apiToken === '') {
                return back()->withErrors([
                    'myfatoorah.api_token' => 'MyFatoorah API token is required when MyFatoorah is enabled for tenant bookings.',
                ])->withInput();
            }

            if (!ctype_digit($paymentMethodId) || (int) $paymentMethodId <= 0) {
                return back()->withErrors([
                    'myfatoorah.payment_method_id' => 'Enter a valid MyFatoorah Payment Method ID (number greater than 0).',
                ])->withInput();
            }
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['payment_gateways'] = [
            'default_provider' => $defaultProvider,
            'stripe' => [
                'enabled' => (bool) data_get($validated, 'stripe.enabled', false),
            ],
            'myfatoorah' => [
                'enabled' => (bool) data_get($validated, 'myfatoorah.enabled', false),
                'country' => strtoupper(trim((string) data_get($validated, 'myfatoorah.country', ''))),
                'api_token' => trim((string) data_get($validated, 'myfatoorah.api_token', '')),
                'api_base_url' => $this->resolveTenantMyFatoorahApiBaseUrl(
                    trim((string) data_get($validated, 'myfatoorah.api_base_url', '')),
                    (string) ($myFatoorahMode ?: 'test')
                ),
                'payment_method_id' => trim((string) data_get($validated, 'myfatoorah.payment_method_id', '')),
                'callback_url' => trim((string) data_get($validated, 'myfatoorah.callback_url', '')),
                'error_url' => trim((string) data_get($validated, 'myfatoorah.error_url', '')),
                'webhook_secret' => trim((string) data_get($validated, 'myfatoorah.webhook_secret', '')),
            ],
            'updated_at' => now()->toDateTimeString(),
        ];

        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Payment provider settings updated successfully.');
    }

    private function resolveTenantMyFatoorahApiBaseUrl(string $value, string $mode): string
    {
        $value = trim($value);
        if ($value !== '') {
            return $value;
        }

        return strtolower($mode) === 'live'
            ? 'https://api.myfatoorah.com'
            : 'https://apitest.myfatoorah.com';
    }
}
