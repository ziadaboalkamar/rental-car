<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $tenant = \App\Models\Tenant::factory()->create(['is_active' => true]);
    $url = 'http://' . $tenant->slug . '.real-rent-car-main.test/forgot-password';

    $response = $this->get($url);

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $tenant = \App\Models\Tenant::factory()->create(['is_active' => true]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $url = 'http://' . $tenant->slug . '.real-rent-car-main.test/forgot-password';

    $this->post($url, ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $tenant = \App\Models\Tenant::factory()->create(['is_active' => true]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $url = 'http://' . $tenant->slug . '.real-rent-car-main.test/forgot-password';

    $this->post($url, ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($tenant) {
        $resetUrl = 'http://' . $tenant->slug . '.real-rent-car-main.test/reset-password/' . $notification->token;
        $response = $this->get($resetUrl);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $tenant = \App\Models\Tenant::factory()->create(['is_active' => true]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $url = 'http://' . $tenant->slug . '.real-rent-car-main.test/forgot-password';

    $this->post($url, ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $tenant) {
        $storeUrl = 'http://' . $tenant->slug . '.real-rent-car-main.test/reset-password';
        $response = $this->post($storeUrl, [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tenant.login', ['subdomain' => $tenant->slug]));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $tenant = \App\Models\Tenant::factory()->create(['is_active' => true]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $url = 'http://' . $tenant->slug . '.real-rent-car-main.test/reset-password';

    $response = $this->post($url, [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});