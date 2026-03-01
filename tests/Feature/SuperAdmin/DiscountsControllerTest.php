<?php namespace Tests\Feature\SuperAdmin;

use App\Enums\UserRole;
use App\Models\Discount;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->withoutMiddleware([
            \App\Http\Middleware\PermissionMiddleware::class,
            \App\Http\Middleware\SuperAdminMiddleware::class,
            \App\Http\Middleware\CheckUserActive::class,
            'verified',
        ]);
    }

    public function test_index_page_is_accessible()
    {
        $this->actingAs($this->user)
            ->get(route('superadmin.discounts.index'))
            ->assertStatus(200);
    }

    public function test_can_create_discount()
    {
        $plan = Plan::create([
            'name' => 'Pro Plan',
            'monthly_price' => 29.99,
            'yearly_price' => 299.99,
            'is_active' => true,
        ]);

        $discountData = [
            'plan_id' => $plan->id,
            'name' => 'Summer Sale',
            'code' => 'SUMMER50',
            'type' => 'percentage',
            'value' => 50,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ];

        $this->actingAs($this->user)
            ->post(route('superadmin.discounts.store'), $discountData)
            ->assertRedirect(route('superadmin.discounts.index'));

        $this->assertDatabaseHas('discounts', [
            'name' => 'Summer Sale',
            'code' => 'SUMMER50',
            'value' => 50.00,
        ]);
    }

    public function test_can_update_discount()
    {
        $plan = Plan::create([
            'name' => 'Pro Plan',
            'monthly_price' => 29.99,
            'yearly_price' => 299.99,
            'is_active' => true,
        ]);

        $discount = Discount::create([
            'plan_id' => $plan->id,
            'name' => 'Old Discount',
            'type' => 'percentage',
            'value' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->put(route('superadmin.discounts.update', $discount), [
                'plan_id' => $plan->id,
                'name' => 'Updated Discount',
                'type' => 'fixed',
                'value' => 15.00,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'is_active' => false,
            ])
            ->assertRedirect(route('superadmin.discounts.index'));

        $this->assertDatabaseHas('discounts', [
            'id' => $discount->id,
            'name' => 'Updated Discount',
            'value' => 15.00,
            'is_active' => false,
        ]);
    }

    public function test_can_delete_discount()
    {
        $plan = Plan::create([
            'name' => 'Pro Plan',
            'monthly_price' => 29.99,
            'yearly_price' => 299.99,
            'is_active' => true,
        ]);

        $discount = Discount::create([
            'plan_id' => $plan->id,
            'name' => 'To be deleted',
            'type' => 'percentage',
            'value' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->delete(route('superadmin.discounts.destroy', $discount))
            ->assertRedirect(route('superadmin.discounts.index'));

        $this->assertDatabaseMissing('discounts', ['id' => $discount->id]);
    }
}
