<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Core\SocialLoginSettings;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('SuperAdmin/Settings/Login', [
            'socialLoginSettings' => SocialLoginSettings::forUi(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'social_login.google.enabled' => ['nullable', 'boolean'],
            'social_login.google.client_id' => ['nullable', 'string', 'max:1000'],
            'social_login.google.client_secret' => ['nullable', 'string', 'max:1000'],

            'social_login.apple.enabled' => ['nullable', 'boolean'],
            'social_login.apple.client_id' => ['nullable', 'string', 'max:1000'],
            'social_login.apple.client_secret' => ['nullable', 'string', 'max:1000'],
        ]);

        $currentSocialLogin = SocialLoginSettings::load();
        $normalizedSocialLogin = SocialLoginSettings::normalize($validated['social_login'] ?? []);
        $normalizedSocialLogin = SocialLoginSettings::mergeSecrets($currentSocialLogin, $normalizedSocialLogin);

        SiteSetting::query()->updateOrCreate(
            ['key' => SocialLoginSettings::KEY],
            ['value' => $normalizedSocialLogin]
        );

        return back()->with('success', 'Login settings updated successfully.');
    }
}
