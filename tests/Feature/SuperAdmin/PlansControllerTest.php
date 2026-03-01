<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PlansControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true, // Ensure user is active to pass CheckUserActive middleware
        ]);
        
        $this->withoutMiddleware([
            \App\Http\Middleware\PermissionMiddleware::class,
            \App\Http\Middleware\SuperAdminMiddleware::class,
            \App\Http\Middleware\CheckUserActive::class,
            // DO NOT disable 'auth' middleware here as it might interfere with actingAs in some scenarios
            'verified',
        ]);
    }

    public function test_index_page_is_accessible()
    {
        $this->actingAs($this->user)
            ->get(route('superadmin.plans.index'))
            ->assertStatus(200);
    }

    public function test_can_create_plan()
    {
        $planData = [
            'name' => 'Pro Plan',
            'description' => 'Test Description',
            'features' => ['Feature 1', 'Feature 2'],
            'monthly_price' => 29.99,
            'monthly_price_id' => 'price_123',
            'yearly_price' => 299.99,
            'yearly_price_id' => 'price_456',
            'is_active' => true,
        ];

        $this->actingAs($this->user)
            ->post(route('superadmin.plans.store'), $planData)
            ->assertRedirect(route('superadmin.plans.index'));

        $this->assertDatabaseHas('plans', [
            'name' => 'Pro Plan',
            'monthly_price' => 29.99,
        ]);
    }

    public function test_can_update_plan()
    {
        $plan = Plan::create([
            'name' => 'Old Plan',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->from(route('superadmin.plans.index'))
            ->put(route('superadmin.plans.update', $plan), [
                'name' => 'Updated Plan',
                'description' => 'Updated Desc',
                'monthly_price' => 20.00,
                'yearly_price' => 200.00,
                'is_active' => false,
                'features' => ['New Feature'],
            ])
            ->assertRedirect(route('superadmin.plans.index'));

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => 'Updated Plan',
            'is_active' => false,
        ]);
    }

    public function test_can_delete_plan()
    {
        $plan = Plan::create([
            'name' => 'To be deleted',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->delete(route('superadmin.plans.destroy', $plan))
            ->assertRedirect(route('superadmin.plans.index'));

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }
}
