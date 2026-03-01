<?php

namespace Database\Seeders;

use App\Models\PaymentProvider;
use Illuminate\Database\Seeder;

class PaymentProvidersSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'code' => 'stripe',
                'name' => 'Stripe',
                'driver' => 'stripe',
                'description' => 'Global card payments. Stripe Connect can be used for supported countries.',
                'is_enabled' => false,
                'is_default' => false,
                'supports_platform_subscriptions' => true,
                'supports_tenant_payments' => true,
                'mode' => 'test',
                'config' => [
                    'notes' => 'Uses platform Stripe settings by default unless overridden.',
                ],
                'supported_countries' => ['AE', 'US', 'GB'],
                'supported_currencies' => ['USD', 'AED'],
                'sort_order' => 10,
            ],
            [
                'code' => 'myfatoorah',
                'name' => 'MyFatoorah',
                'driver' => 'myfatoorah',
                'description' => 'Strong GCC coverage. Good option for Oman and MENA hosted checkout.',
                'is_enabled' => false,
                'is_default' => false,
                'supports_platform_subscriptions' => true,
                'supports_tenant_payments' => true,
                'mode' => 'test',
                'config' => [
                    'country' => 'OM',
                    'api_base_url' => '',
                    'api_token' => '',
                ],
                'supported_countries' => ['OM', 'AE', 'SA', 'KW', 'QA', 'BH'],
                'supported_currencies' => ['OMR', 'AED', 'SAR', 'KWD', 'QAR', 'BHD', 'USD'],
                'sort_order' => 20,
            ],
        ];

        foreach ($providers as $provider) {
            PaymentProvider::query()->updateOrCreate(
                ['code' => $provider['code']],
                $provider
            );
        }

        PaymentProvider::query()
            ->whereNotIn('code', ['stripe', 'myfatoorah'])
            ->delete();
    }
}
