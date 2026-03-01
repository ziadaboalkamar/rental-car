<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class TenantsController
{
    /**
     * Display a listing of tenants.
     */
    public function index(): Response
    {
        $tenants = Tenant::query()
            ->with('subscriptionPlan:id,name')
            ->withCount(['users', 'cars', 'reservations'])
            ->latest()
            ->paginate(20);

        return Inertia::render('SuperAdmin/Tenants/Index', [
            'tenants' => $tenants,
        ]);
    }

    /**
     * Show the form for creating a new tenant.
     */
    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Tenants/Create', [
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('monthly_price')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created tenant in storage and create an admin user for dashboard login.
     */
    public function store(Request $request)
    {
        $request->merge([
            'slug' => Str::slug((string) $request->input('slug')),
            'domain' => $this->normalizeDomain($request->input('domain')),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug',
            'domain' => ['nullable', 'string', 'max:255', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', 'unique:tenants,domain'],
            'email' => 'required|email|unique:tenants,email',
            'phone' => 'nullable|string|max:20',
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where(static fn ($query) => $query->where('is_active', true))],
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $tenantData = [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'domain' => $validated['domain'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'plan_id' => (int) $validated['plan_id'],
            'trial_ends_at' => now()->addMonth(),
            'is_active' => true,
        ];

        $tenant = Tenant::create($tenantData);

        User::withoutGlobalScope('tenant')->create([
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($validated['admin_password']),
            'role' => UserRole::ADMIN,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('superadmin.tenants.show', $tenant)
            ->with('success', 'Tenant and admin user created.');
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenant $tenant): Response
    {
        $tenant->load('subscriptionPlan:id,name');
        $tenant->loadCount(['users', 'cars', 'reservations', 'payments']);
        $tenant->load([
            'users' => fn($query) => $query->latest()->take(5),
            'reservations' => fn($query) => $query->latest()->take(5),
        ]);

        return Inertia::render('SuperAdmin/Tenants/Show', [
            'tenant' => $tenant,
        ]);
    }

    /**
     * Show the form for editing the specified tenant.
     */
    public function edit(Tenant $tenant): Response
    {
        $adminUser = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('role', UserRole::ADMIN)
            ->first();

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('SuperAdmin/Tenants/Edit', [
            'tenant' => $tenant,
            'plans' => $plans,
            'admin_user' => $adminUser ? [
                'id' => $adminUser->id,
                'name' => $adminUser->name,
                'email' => $adminUser->email,
            ] : null,
        ]);
    }

    /**
     * Update the specified tenant in storage.
     */
    public function update(Request $request, Tenant $tenant)
    {
        $request->merge([
            'slug' => Str::slug((string) $request->input('slug')),
            'domain' => $this->normalizeDomain($request->input('domain')),
        ]);

        $rules = [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug,' . $tenant->id,
            'domain' => ['nullable', 'string', 'max:255', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', 'unique:tenants,domain,' . $tenant->id],
            'email' => 'required|email|unique:tenants,email,' . $tenant->id,
            'phone' => 'nullable|string|max:20',
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where(static fn ($query) => $query->where('is_active', true))],
            'is_active' => 'required|boolean',
        ];

        if ($request->filled('admin_password')) {
            $rules['admin_password'] = ['required', 'confirmed', Rules\Password::defaults()];
        }

        $validated = $request->validate($rules);

        $tenant->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'domain' => $validated['domain'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'plan_id' => (int) $validated['plan_id'],
            'is_active' => $validated['is_active'],
        ]);

        if (!empty($validated['admin_password'])) {
            $adminUser = User::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('role', UserRole::ADMIN)
                ->first();

            if ($adminUser) {
                $adminUser->update(['password' => Hash::make($validated['admin_password'])]);
            }
        }

        return redirect()
            ->route('superadmin.tenants.show', $tenant)
            ->with('success', 'Tenant updated successfully.');
    }

    /**
     * Remove the specified tenant from storage.
     */
    public function destroy(Tenant $tenant)
    {
        $tenant->delete();

        return redirect()
            ->route('superadmin.tenants.index')
            ->with('success', 'Tenant deleted successfully.');
    }

    private function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }

        $normalized = strtolower(trim($domain));
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('#^https?://#', '', $normalized) ?? $normalized;
        $normalized = explode('/', $normalized)[0] ?? $normalized;
        $normalized = preg_replace('/:\d+$/', '', $normalized) ?? $normalized;
        $normalized = trim($normalized, '.');

        if (str_starts_with($normalized, 'www.')) {
            $normalized = substr($normalized, 4);
        }

        return $normalized !== '' ? $normalized : null;
    }
}
