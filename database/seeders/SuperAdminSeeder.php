<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if super admin already exists
        $existingSuperAdmin = User::withoutGlobalScope('tenant')
            ->where('role', UserRole::SUPER_ADMIN)
            ->first();
        
        if ($existingSuperAdmin) {
            $this->command->info('Super Admin already exists: ' . $existingSuperAdmin->email);
            return;
        }

        // Create Super Admin user
        $user = User::withoutGlobalScope('tenant')->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::SUPER_ADMIN,
            'tenant_id' => null, // Super Admin has no tenant
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->command->info('✅ Super Admin created successfully!');
        $this->command->info('Email: ' . $user->email);
        $this->command->info('Password: password');
        $this->command->info('');
        $this->command->info('You can now login and access /superadmin');
    }
}
