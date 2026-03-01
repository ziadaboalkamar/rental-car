<?php

namespace App\Http\Middleware;

use App\Models\TenantSiteSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'locale' => app()->getLocale(),
            'direction' => LaravelLocalization::getCurrentLocaleDirection(),
            'available_locales' => LaravelLocalization::getSupportedLanguagesKeys(),
            'translations' => __('site'),
            'auth' => [
                'user' => $request->user()?->load('roles.permissions'),
                'permissions' => $request->user()?->allPermissions()->pluck('name') ?? [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'csrf_token' => csrf_token(),
            'fileUploadConfig' => [
                'locale' => config('vilt-filepond.locale'),
                'chunkSize' => config('vilt-filepond.chunk_size'),
            ],
            'currency' =>[
                'symbol' => config('app.currency_symbol'),
                'code' => config('app.currency_code'),
            ],
            'app_url_base' => parse_url(config('app.url'), PHP_URL_HOST),
            'current_tenant' => \App\Core\TenantContext::get(),
            'tenant_site_settings' => function () {
                $tenant = \App\Core\TenantContext::get();

                if (!$tenant) {
                    return null;
                }

                $tenant->loadMissing('siteSetting.files');

                return TenantSiteSetting::forTenant($tenant);
            },
            'flash' => [
                'restricted_action' => $request->session()->get('restricted_action'),
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
