<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('two factor challenge redirects to login when not authenticated', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $tenant = \App\Models\Tenant::factory()->create(['is_active' => true]);
    $url = 'http://' . $tenant->slug . '.real-rent-car-main.test/two-factor-challenge';

    $response = $this->get($url);

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $plan = \App\Models\Plan::factory()->create(['is_active' => true]);
    $tenant = \App\Models\Tenant::factory()->create([
        'is_active' => true,
        'plan_id' => $plan->id,
    ]);
    
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => \App\Enums\UserRole::ADMIN,
    ]);

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $loginUrl = 'http://' . $tenant->slug . '.real-rent-car-main.test/login';
    $challengeUrl = 'http://' . $tenant->slug . '.real-rent-car-main.test/two-factor-challenge';

    $this->post($loginUrl, [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->get($challengeUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/TwoFactorChallenge')
        );
});