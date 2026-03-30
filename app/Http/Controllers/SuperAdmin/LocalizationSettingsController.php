<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Core\LocalizationSettings;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LocalizationSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = LocalizationSettings::load();

        return Inertia::render('SuperAdmin/Settings/Languages', [
            'settings' => $settings,
            'actions' => [
                'update' => route('superadmin.settings.languages.update'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $currentLocale = (string) app()->getLocale();

        $validated = $request->validate([
            'default_locale' => ['required', 'string', 'max:10'],
            'locales' => ['required', 'array', 'min:1'],
            'locales.*.code' => ['required', 'string', 'max:10', 'distinct', 'regex:/^[a-zA-Z]{2,3}(?:[-_][a-zA-Z]{2})?$/'],
            'locales.*.name' => ['required', 'string', 'max:100'],
            'locales.*.native' => ['required', 'string', 'max:100'],
            'locales.*.regional' => ['nullable', 'string', 'max:10', 'regex:/^[a-zA-Z]{2}[_-][a-zA-Z]{2}$/'],
            'locales.*.script' => ['nullable', 'string', 'max:15'],
            'locales.*.direction' => ['required', Rule::in(['ltr', 'rtl'])],
        ]);

        $normalized = LocalizationSettings::normalize([
            'default_locale' => $validated['default_locale'],
            'locales' => $validated['locales'],
        ]);

        SiteSetting::query()->updateOrCreate(
            ['key' => LocalizationSettings::KEY],
            ['value' => $normalized]
        );

        $request->session()->put('locale', $currentLocale);

        return redirect()
            ->to($this->localizedCurrentPath($request, $currentLocale, $normalized['default_locale'], LocalizationSettings::localeCodes($normalized)))
            ->with('success', 'Language settings updated successfully.');
    }

    private function localizedCurrentPath(Request $request, string $currentLocale, string $defaultLocale, array $supportedLocales): string
    {
        $path = '/'.ltrim($request->path(), '/');
        $query = $request->getQueryString();

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

        if ($currentLocale !== $defaultLocale) {
            $normalizedPath = '/'.$currentLocale.($normalizedPath === '/' ? '' : $normalizedPath);
        }

        return $query ? $normalizedPath.'?'.$query : $normalizedPath;
    }
}
