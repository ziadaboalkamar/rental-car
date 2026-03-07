<?php

namespace Tests\Feature;

use App\Enums\CarStatus;
use App\Enums\MaintenanceRecordStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Car;
use App\Models\CarMaintenance;
use App\Models\MaintenanceType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceScheduleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_starts_due_maintenance_and_creates_notification(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'email' => 'tenant-a@example.com',
            'plan' => 'basic',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'license_plate' => 'TEST-1001',
            'color' => 'white',
            'price_per_day' => 50,
            'mileage' => 1000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => 'gasoline',
            'status' => CarStatus::AVAILABLE->value,
        ]);

        $type = MaintenanceType::create([
            'tenant_id' => $tenant->id,
            'name' => 'Oil Change',
            'is_active' => true,
        ]);

        $maintenance = CarMaintenance::create([
            'tenant_id' => $tenant->id,
            'car_id' => $car->id,
            'branch_id' => $branch->id,
            'maintenance_type_id' => $type->id,
            'status' => MaintenanceRecordStatus::SCHEDULED->value,
            'scheduled_date' => today()->toDateString(),
            'created_by' => $admin->id,
        ]);

        $this->artisan('maintenance:process-schedule')
            ->assertExitCode(0);

        $maintenance->refresh();
        $car->refresh();
        $admin->refresh();

        $this->assertSame(MaintenanceRecordStatus::IN_PROGRESS, $maintenance->status);
        $this->assertNotNull($maintenance->started_at);
        $this->assertSame(CarStatus::MAINTENANCE, $car->status);
        $this->assertGreaterThan(0, $admin->notifications()->count());
    }

    public function test_command_completes_finished_maintenance_and_returns_car_available(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'email' => 'tenant-b@example.com',
            'plan' => 'basic',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Service Branch',
        ]);

        $car = Car::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'make' => 'Honda',
            'model' => 'Accord',
            'year' => 2023,
            'license_plate' => 'TEST-2002',
            'color' => 'black',
            'price_per_day' => 65,
            'mileage' => 2000,
            'transmission' => 'automatic',
            'seats' => 5,
            'fuel_type' => 'gasoline',
            'status' => CarStatus::MAINTENANCE->value,
        ]);

        $type = MaintenanceType::create([
            'tenant_id' => $tenant->id,
            'name' => 'Brake Service',
            'is_active' => true,
        ]);

        $maintenance = CarMaintenance::create([
            'tenant_id' => $tenant->id,
            'car_id' => $car->id,
            'branch_id' => $branch->id,
            'maintenance_type_id' => $type->id,
            'status' => MaintenanceRecordStatus::IN_PROGRESS->value,
            'started_at' => now()->subHours(3),
            'completed_at' => now()->subMinutes(10),
            'created_by' => $admin->id,
        ]);

        $this->artisan('maintenance:process-schedule')
            ->assertExitCode(0);

        $maintenance->refresh();
        $car->refresh();
        $admin->refresh();

        $this->assertSame(MaintenanceRecordStatus::COMPLETED, $maintenance->status);
        $this->assertSame(CarStatus::AVAILABLE, $car->status);
        $this->assertGreaterThan(0, $admin->notifications()->count());
    }
}

