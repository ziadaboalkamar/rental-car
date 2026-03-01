<?php

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;

// Create Super Admin user
$user = User::create([
    'name' => 'Super Admin',
    'email' => 'superadmin@test.com',
    'password' => Hash::make('password'),
    'role' => UserRole::SUPER_ADMIN,
    'tenant_id' => null, // Super Admin has no tenant
    'is_active' => true,
    'email_verified_at' => now(),
]);

echo "✅ Super Admin created successfully!\n";
echo "Email: {$user->email}\n";
echo "Password: password\n";
echo "\nYou can now login and access /superadmin\n";
