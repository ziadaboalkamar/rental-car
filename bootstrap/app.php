<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request): string {
            $host = strtolower($request->getHost());
            $baseHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));

            if ($baseHost !== '' && str_ends_with($host, '.'.$baseHost)) {
                return route('tenant-login');
            }

            return route('login');
        });

        $middleware->web(append: [
            \App\Http\Middleware\IdentifyTenant::class,
            \App\Http\Middleware\SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'payment/webhooks/subscriptions/*',
        ]);

        $middleware->alias([
            'super_admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'client' => \App\Http\Middleware\ClientMiddleware::class,
            'active' => \App\Http\Middleware\CheckUserActive::class,
            'tenant.subscription' => \App\Http\Middleware\EnsureTenantSubscriptionIsActive::class,
            'restricted' => \App\Http\Middleware\restricted::class,
            'can_manage_roles' => \App\Http\Middleware\CanManageRoles::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
             /**** OTHER MIDDLEWARE ALIASES ****/
            'localize'                => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes::class,
            'localizationRedirect'    => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            'localeSessionRedirect'   => \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            'localeCookieRedirect'    => \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            'localeViewPath'          => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath::class,
     
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
