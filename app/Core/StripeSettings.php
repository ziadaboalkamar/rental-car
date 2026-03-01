<?php

namespace App\Core;

class StripeSettings
{
    public const KEY = 'stripe_settings';

    /**
     * Default Stripe / Cashier settings used when DB value is empty.
     */
    public static function defaults(): array
    {
        return [
            'key' => '',
            'secret' => '',
            'webhook_secret' => '',
            'webhook_tolerance' => 300,
            'currency' => strtolower((string) config('cashier.currency', 'usd')),
            'currency_locale' => (string) config('cashier.currency_locale', 'en'),
            'path' => trim((string) config('cashier.path', 'stripe'), '/'),
            'logger' => (string) config('cashier.logger', ''),
        ];
    }

    /**
     * Normalize incoming data to always match expected structure.
     */
    public static function normalize(?array $data): array
    {
        $settings = array_replace(self::defaults(), is_array($data) ? $data : []);

        $settings['key'] = trim((string) ($settings['key'] ?? ''));
        $settings['secret'] = trim((string) ($settings['secret'] ?? ''));
        $settings['webhook_secret'] = trim((string) ($settings['webhook_secret'] ?? ''));
        $settings['webhook_tolerance'] = max(0, (int) ($settings['webhook_tolerance'] ?? 300));
        $settings['currency'] = strtolower(trim((string) ($settings['currency'] ?? 'usd')));
        $settings['currency_locale'] = trim((string) ($settings['currency_locale'] ?? 'en'));
        $settings['path'] = trim((string) ($settings['path'] ?? 'stripe'), '/');
        $settings['logger'] = trim((string) ($settings['logger'] ?? ''));

        if ($settings['currency'] === '') {
            $settings['currency'] = 'usd';
        }

        if ($settings['currency_locale'] === '') {
            $settings['currency_locale'] = 'en';
        }

        if ($settings['path'] === '') {
            $settings['path'] = 'stripe';
        }

        return $settings;
    }
}
