<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RolesController
{
    private function superAdminPermissionsQuery()
    {
        return Permission::withoutGlobalScope('tenant')
            ->whereNull('tenant_id')
            ->where('name', 'not like', 'tenant-%');
    }

    /**
     * List all roles with their permissions.
     */
    public function index(): Response
    {
        $roles = Role::withoutGlobalScope('tenant')
            ->whereNull('tenant_id')
            ->with(['permissions' => fn ($query) => $query
                ->withoutGlobalScope('tenant')
                ->whereNull('permissions.tenant_id')
                ->where('permissions.name', 'not like', 'tenant-%')
                ->select('permissions.id', 'permissions.name', 'permissions.display_name')])
            ->orderBy('name')
            ->get();

        return Inertia::render('SuperAdmin/Roles/Index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show form to create a new role (with permission checkboxes).
     */
    public function create(): Response
    {
        $permissions = $this->superAdminPermissionsQuery()
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'description']);

        return Inertia::render('SuperAdmin/Roles/Create', [
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a new role and assign permissions.
     */
public function store(Request $request)
{
    $allowedPermissionIds = $this->superAdminPermissionsQuery()
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->all();

    $validated = $request->validate([
        'name' => [
            'required',
            'string',
            'max:255',
            Rule::unique('roles', 'name')->where(fn ($query) => $query->whereNull('tenant_id')),
        ],
        'display_name' => 'nullable|string|max:255',
        'description' => 'nullable|string|max:500',
        'permission_ids' => 'array',
        'permission_ids.*' => ['integer', Rule::in($allowedPermissionIds)],
    ]);

    $role = Role::withoutGlobalScope('tenant')->create([
        'name' => $validated['name'],
        'display_name' => $validated['display_name'] ?? null,
        'description' => $validated['description'] ?? null,
        'tenant_id' => null,
    ]);

    // Laratrust uses syncPermissions() - but it expects Permission models or IDs
    if (!empty($validated['permission_ids'])) {
        $role->syncPermissions($validated['permission_ids']);
    }

    return redirect()
        ->route('superadmin.roles.index')
        ->with('success', 'Role created successfully with permissions.');
}

    /**
     * Show form to edit role and its permissions.
     */
    public function edit(Role $role): Response
    {
        abort_unless($role->tenant_id === null, 404);

        $allowedPermissionIds = $this->superAdminPermissionsQuery()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $role->load(['permissions' => fn ($query) => $query
            ->withoutGlobalScope('tenant')
            ->whereIn('permissions.id', $allowedPermissionIds)]);
        $permissions = $this->superAdminPermissionsQuery()
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'description']);

        return Inertia::render('SuperAdmin/Roles/Edit', [
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Update role and its permissions.
     */
    public function update(Request $request, Role $role)
    {
        abort_unless($role->tenant_id === null, 404);

        $allowedPermissionIds = $this->superAdminPermissionsQuery()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->ignore($role->id)
                    ->where(fn ($query) => $query->whereNull('tenant_id')),
            ],
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'permission_ids' => 'array',
            'permission_ids.*' => ['integer', Rule::in($allowedPermissionIds)],
        ]);

        $role->update([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        $permissionIds = collect($validated['permission_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->intersect($allowedPermissionIds)
            ->unique()
            ->values()
            ->all();

        $role->syncPermissions($permissionIds);

        return redirect()
            ->route('superadmin.roles.index')
            ->with('success', 'Role updated.');
    }

    /**
     * Delete a role.
     */
    public function destroy(Role $role)
    {
        abort_unless($role->tenant_id === null, 404);

        $role->delete();

        return redirect()
            ->route('superadmin.roles.index')
            ->with('success', 'Role deleted.');
    }
}
