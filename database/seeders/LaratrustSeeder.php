<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class LaratrustSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createPermissions();
        $this->createRoles();
        $this->assignSuperAdminRole();
    }

    private function createPermissions(): void
    {
        $permissions = [
            ['name' => 'view-dashboard', 'display_name' => 'View Dashboard', 'description' => 'Access Super Admin dashboard'],
            ['name' => 'manage-tenants', 'display_name' => 'Manage Tenants', 'description' => 'Create, edit, delete tenants'],
            ['name' => 'manage-users', 'display_name' => 'Manage Users', 'description' => 'Create and manage Super Admin users'],
            ['name' => 'manage-roles', 'display_name' => 'Manage Roles', 'description' => 'Create and assign roles'],
            ['name' => 'manage-permissions', 'display_name' => 'Manage Permissions', 'description' => 'Create and assign permissions'],
            ['name' => 'manage-settings', 'display_name' => 'Manage Settings', 'description' => 'General and system settings'],
            ['name' => 'manage-revenue', 'display_name' => 'Manage Revenue', 'description' => 'Subscriptions and transactions'],
            ['name' => 'manage-cars', 'display_name' => 'Manage Cars', 'description' => 'View all cars across tenants'],
            ['name' => 'manage-reservations', 'display_name' => 'Manage Reservations', 'description' => 'View all reservations'],
        ];

        foreach ($permissions as $perm) {
            Permission::withoutGlobalScope('tenant')->firstOrCreate(
                ['name' => $perm['name']],
                $perm
            );
        }
    }

    private function createRoles(): void
    {
        $superAdmin = Role::withoutGlobalScope('tenant')->firstOrCreate(
            ['name' => 'super-admin'],
            ['display_name' => 'Super Administrator', 'description' => 'Full access to Super Admin area']
        );
        $superAdmin->syncPermissions(Permission::withoutGlobalScope('tenant')->pluck('id'));

        Role::withoutGlobalScope('tenant')->firstOrCreate(
            ['name' => 'manager'],
            [
                'display_name' => 'Manager',
                'description' => 'Can manage tenants and view reports',
            ]
        )->syncPermissions(
            Permission::withoutGlobalScope('tenant')
                ->whereIn('name', ['view-dashboard', 'manage-tenants', 'manage-cars', 'manage-reservations', 'manage-revenue', 'manage-roles', 'manage-permissions'])
                ->pluck('id')
        );

        Role::withoutGlobalScope('tenant')->firstOrCreate(
            ['name' => 'sub-admin'],
            [
                'display_name' => 'Sub Admin',
                'description' => 'Can manage roles and assign permissions',
            ]
        )->syncPermissions(
            Permission::withoutGlobalScope('tenant')
                ->whereIn('name', ['view-dashboard', 'manage-roles', 'manage-permissions'])
                ->pluck('id')
        );
    }

    private function assignSuperAdminRole(): void
    {
        $role = Role::withoutGlobalScope('tenant')->where('name', 'super-admin')->first();
        if (!$role) {
            return;
        }

        User::withoutGlobalScope('tenant')
            ->where('role', UserRole::SUPER_ADMIN)
            ->each(fn (User $user) => $user->addRole($role));
    }
}
