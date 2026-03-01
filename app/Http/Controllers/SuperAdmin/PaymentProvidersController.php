<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PaymentProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentProvidersController extends Controller
{
    public function index(): Response
    {
        $providers = PaymentProvider::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PaymentProvider $provider) => [
                'id' => $provider->id,
                'code' => $provider->code,
                'name' => $provider->name,
                'driver' => $provider->driver,
                'description' => $provider->description,
                'is_enabled' => (bool) $provider->is_enabled,
                'is_default' => (bool) $provider->is_default,
                'supports_platform_subscriptions' => (bool) $provider->supports_platform_subscriptions,
                'supports_tenant_payments' => (bool) $provider->supports_tenant_payments,
                'mode' => $provider->mode,
                'config' => $provider->config ?? [],
                'supported_countries' => $provider->supported_countries ?? [],
                'supported_currencies' => $provider->supported_currencies ?? [],
                'sort_order' => (int) $provider->sort_order,
                'last_tested_at' => optional($provider->last_tested_at)?->toDateTimeString(),
                'updated_at' => optional($provider->updated_at)?->toDateTimeString(),
            ])
            ->values();

        return Inertia::render('SuperAdmin/Settings/PaymentProviders', [
            'providers' => $providers,
        ]);
    }

    public function update(Request $request, PaymentProvider $paymentProvider): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'is_enabled' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'supports_platform_subscriptions' => ['required', 'boolean'],
            'supports_tenant_payments' => ['required', 'boolean'],
            'mode' => ['required', 'in:test,live'],
            'config' => ['nullable', 'array'],
            'config.*' => ['nullable'],
            'supported_countries' => ['nullable', 'array'],
            'supported_countries.*' => ['string', 'max:10'],
            'supported_currencies' => ['nullable', 'array'],
            'supported_currencies.*' => ['string', 'max:10'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $normalizedConfig = $this->normalizeConfigArray($validated['config'] ?? []);
        if ($paymentProvider->code === 'myfatoorah') {
            $normalizedConfig = $this->applyMyFatoorahDefaults($normalizedConfig, (string) $validated['mode']);
        }
        $countries = $this->normalizeStringArray($validated['supported_countries'] ?? []);
        $currencies = array_map('strtoupper', $this->normalizeStringArray($validated['supported_currencies'] ?? []));

        if (($validated['is_default'] ?? false) === true) {
            PaymentProvider::query()
                ->whereKeyNot($paymentProvider->id)
                ->update(['is_default' => false]);
        }

        $paymentProvider->update([
            'name' => $validated['name'],
            'driver' => $validated['driver'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_enabled' => (bool) $validated['is_enabled'],
            'is_default' => (bool) $validated['is_default'],
            'supports_platform_subscriptions' => (bool) $validated['supports_platform_subscriptions'],
            'supports_tenant_payments' => (bool) $validated['supports_tenant_payments'],
            'mode' => $validated['mode'],
            'config' => $normalizedConfig,
            'supported_countries' => $countries,
            'supported_currencies' => $currencies,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return back()->with('success', sprintf('%s updated successfully.', $paymentProvider->name));
    }

    protected function normalizeStringArray(array $values): array
    {
        return array_values(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $values), fn ($value) => $value !== ''));
    }

    protected function normalizeConfigArray(array $config): array
    {
        $normalized = [];

        foreach ($config as $key => $value) {
            $key = trim((string) $key);

            if ($key === '') {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    protected function applyMyFatoorahDefaults(array $config, string $mode): array
    {
        $apiBaseUrl = trim((string) ($config['api_base_url'] ?? ''));
        if ($apiBaseUrl === '') {
            $config['api_base_url'] = $mode === 'live'
                ? 'https://api.myfatoorah.com'
                : 'https://apitest.myfatoorah.com';
        }

        foreach (['callback_url', 'error_url'] as $key) {
            $value = trim((string) ($config[$key] ?? ''));
            $config[$key] = $value === '' ? null : $value;
        }

        return $config;
    }
}
