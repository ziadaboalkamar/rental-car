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

        return back()->with('success', 'Language settings updated successfully.');
    }
}
