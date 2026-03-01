<?php

namespace App\Providers;

use App\Auth\TenantAwareUserProvider;
use App\Core\AiProviderSettings;
use App\Core\StripeSettings;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Avoid stale/locked public/hot forcing broken dev-server URLs in production-like runs.
        Vite::useHotFile(storage_path('framework/vite.hot'));

        $this->applyStripeSettingsFromDatabase();
        $this->applyOpenAiSettingsFromDatabase();

        // Register a tenant-aware user provider that ignores tenant scopes during auth lookups.
        Auth::provider('eloquent-tenant-aware', function ($app, array $config) {
            return new TenantAwareUserProvider(
                $app['hash'],
                $config['model'] ?? \App\Models\User::class
            );
        });
    }

    private function applyStripeSettingsFromDatabase(): void
    {
        try {
            if (!Schema::hasTable('site_settings')) {
                return;
            }

            $stored = SiteSetting::query()
                ->where('key', StripeSettings::KEY)
                ->value('value');

            $settings = StripeSettings::normalize(is_array($stored) ? $stored : null);

            config([
                'cashier.key' => $settings['key'] !== '' ? $settings['key'] : config('cashier.key'),
                'cashier.secret' => $settings['secret'] !== '' ? $settings['secret'] : config('cashier.secret'),
                'cashier.webhook.secret' => $settings['webhook_secret'] !== '' ? $settings['webhook_secret'] : config('cashier.webhook.secret'),
                'cashier.webhook.tolerance' => $settings['webhook_tolerance'],
                'cashier.currency' => $settings['currency'],
                'cashier.currency_locale' => $settings['currency_locale'],
                'cashier.path' => $settings['path'],
                'cashier.logger' => $settings['logger'] !== '' ? $settings['logger'] : config('cashier.logger'),
            ]);
        } catch (Throwable) {
            // Do not block app boot if settings table is not ready yet.
        }
    }

    private function applyOpenAiSettingsFromDatabase(): void
    {
        try {
            if (!Schema::hasTable('site_settings')) {
                return;
            }

            $settings = AiProviderSettings::load();
            $openAi = $settings['openai'] ?? [];

            config([
                'openai.api_key' => ($openAi['api_key'] ?? '') !== '' ? $openAi['api_key'] : config('openai.api_key'),
                'openai.organization' => ($openAi['organization'] ?? '') !== '' ? $openAi['organization'] : config('openai.organization'),
                'openai.project' => ($openAi['project'] ?? '') !== '' ? $openAi['project'] : config('openai.project'),
                'openai.base_uri' => ($openAi['base_uri'] ?? '') !== '' ? $openAi['base_uri'] : config('openai.base_uri'),
            ]);
        } catch (Throwable) {
            // Do not block app boot if settings table is not ready yet.
        }
    }
}
