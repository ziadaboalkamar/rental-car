<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Core\StripeSettings;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StripeSettingsController extends Controller
{
    public function edit(): Response
    {
        $stored = SiteSetting::query()
            ->where('key', StripeSettings::KEY)
            ->value('value');

        return Inertia::render('SuperAdmin/Settings/Stripe', [
            'settings' => StripeSettings::normalize(is_array($stored) ? $stored : null),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings.key' => ['nullable', 'string', 'max:255'],
            'settings.secret' => ['nullable', 'string', 'max:255'],
            'settings.webhook_secret' => ['nullable', 'string', 'max:255'],
            'settings.webhook_tolerance' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'settings.currency' => ['required', 'string', 'size:3'],
            'settings.currency_locale' => ['required', 'string', 'max:10'],
            'settings.path' => ['required', 'string', 'max:120'],
            'settings.logger' => ['nullable', 'string', 'max:120'],
        ]);

        $normalized = StripeSettings::normalize($validated['settings'] ?? []);

        SiteSetting::query()->updateOrCreate(
            ['key' => StripeSettings::KEY],
            ['value' => $normalized]
        );

        return back()->with('success', 'Stripe settings updated successfully.');
    }
}
