<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = LaravelLocalization::getSupportedLanguagesKeys();
        $fallback = config('app.fallback_locale', config('app.locale', 'en'));

        $locale = $request->session()->get('locale', config('app.locale', $fallback));
        if (!in_array($locale, $supported, true)) {
            $locale = $fallback;
        }

        LaravelLocalization::setLocale($locale);

        return $next($request);
    }
}
