<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(LaratrustSeeder::class);

        $superAdminRole = Role::withoutGlobalScope('tenant')
            ->where('name', 'super-admin')
            ->first();

        $existingSuperAdmin = User::withoutGlobalScope('tenant')
            ->where('role', UserRole::SUPER_ADMIN)
            ->first();

        if ($existingSuperAdmin) {
            if ($superAdminRole) {
                $existingSuperAdmin->addRole($superAdminRole);
            }

            $this->command->info('Super Admin already exists: ' . $existingSuperAdmin->email);
            return;
        }

        $user = User::withoutGlobalScope('tenant')->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::SUPER_ADMIN,
            'tenant_id' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        if ($superAdminRole) {
            $user->addRole($superAdminRole);
        }

        $this->command->info('Super Admin created successfully.');
        $this->command->info('Email: ' . $user->email);
        $this->command->info('Password: password');
        $this->command->info('');
        $this->command->info('You can now login and access /superadmin');
    }
}
