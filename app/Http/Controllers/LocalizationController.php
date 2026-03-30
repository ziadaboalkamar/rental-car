<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class LocalizationController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $supported = LaravelLocalization::getSupportedLanguagesKeys();
        if (!in_array($locale, $supported, true)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);
        LaravelLocalization::setLocale($locale);

        $redirect = (string) $request->query('redirect', '');
        if ($redirect !== '' && str_starts_with($redirect, '/')) {
            return redirect()->to($this->localizedRedirectPath($redirect, $locale, $supported));
        }

        return redirect()->back();
    }

    private function localizedRedirectPath(string $redirect, string $targetLocale, array $supportedLocales): string
    {
        $parts = parse_url($redirect);
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        $escapedLocales = array_map(
            static fn (string $locale): string => preg_quote($locale, '#'),
            $supportedLocales,
        );

        $normalizedPath = preg_replace(
            '#^/('.implode('|', $escapedLocales).')(?=/|$)#',
            '',
            $path,
            1,
        ) ?: $path;

        $normalizedPath = '/'.ltrim($normalizedPath, '/');

        $defaultLocale = config('app.locale', 'en');
        $shouldHideLocale = (bool) config('laravellocalization.hideDefaultLocaleInURL', false);

        if (!($shouldHideLocale && $targetLocale === $defaultLocale)) {
            $normalizedPath = '/'.$targetLocale.($normalizedPath === '/' ? '' : $normalizedPath);
        }

        return $normalizedPath.$query.$fragment;
    }
}
