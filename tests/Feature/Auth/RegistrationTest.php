<?php

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register', function () {
    $tenant = \App\Models\Tenant::factory()->create(['is_active' => true]);
    $url = 'http://' . $tenant->slug . '.real-rent-car-main.test/register';
    
    $response = $this->from($url)
        ->post($url, [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('client.home', ['subdomain' => $tenant->slug]));
});