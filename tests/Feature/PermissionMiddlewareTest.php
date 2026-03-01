<?php

namespace Tests\Feature;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Laratrust\Models\Permission;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'permission:test-permission'])
            ->get('/_test/protected-route', function () {
                return 'Access Granted';
            });
    }

    public function test_user_without_permission_is_redirected()
    {
        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
        ]);

        $response = $this->actingAs($user)->get('/_test/protected-route');

        $response->assertRedirect(route('superadmin.dashboard'));
        $response->assertSessionHas('restricted_action');
    }

    public function test_user_with_permission_can_access()
    {
        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
        ]);

        $permission = Permission::create(['name' => 'test-permission', 'display_name' => 'Test Permission']);
        $user->givePermission($permission);

        $response = $this->actingAs($user)->get('/_test/protected-route');

        $response->assertStatus(200);
        $response->assertSee('Access Granted');
    }
}
