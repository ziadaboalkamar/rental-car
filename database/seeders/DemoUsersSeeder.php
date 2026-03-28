<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Use the first active tenant (if any) for demo users.
        $tenantId = Tenant::query()->where('is_active', true)->value('id');

        // Admin user
        User::query()->withoutGlobalScope('tenant')->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('00000000'),
                'role' => UserRole::ADMIN,
                'tenant_id' => $tenantId,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Client user
        User::query()->withoutGlobalScope('tenant')->updateOrCreate(
            ['email' => 'client@example.com'],
            [
                'name' => 'Client User',
                'password' => Hash::make('00000000'),
                'role' => UserRole::CLIENT,
                'tenant_id' => $tenantId,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
