<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RolesController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->withCount(['permissions'])
            ->orderBy('display_name')
            ->get();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        $permissions = Permission::withoutGlobalScope('tenant')
            ->whereNull('tenant_id')
            ->where('name', 'like', 'tenant-%')
            ->orderBy('display_name')
            ->get(['id', 'name', 'display_name', 'description']);

        return Inertia::render('Admin/Roles/Edit', [
            'role' => null,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        // Demo mode restriction
        if (config('app.demo_mode')) {
            return redirect()->back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => [
                Rule::exists('permissions', 'id')->where(fn ($query) => $query
                    ->whereNull('tenant_id')
                    ->where('name', 'like', 'tenant-%')),
            ],
        ]);

        $name = Str::slug($validated['display_name']);
        // Ensure name is unique for this tenant
        $count = Role::where('name', $name)->count();
        if ($count > 0) {
            $name .= '-' . time();
        }

        $role = Role::create([
            'name' => $name,
            'display_name' => $validated['display_name'],
            'description' => $validated['description'],
            'tenant_id' => $this->tenantId(),
        ]);

        $permissionIds = collect($validated['permission_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $role->permissions()->sync($permissionIds);

        return redirect()
            ->route('admin.roles.index', ['subdomain' => request('subdomain')])
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role): Response
    {
        $role->load([
            'permissions' => fn ($query) => $query->select('permissions.id'),
        ]);
        
        $permissions = Permission::withoutGlobalScope('tenant')
            ->whereNull('tenant_id')
            ->where('name', 'like', 'tenant-%')
            ->orderBy('display_name')
            ->get(['id', 'name', 'display_name', 'description']);

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'permission_ids' => $role->permissions->pluck('id')->toArray(),
            ],
            'permissions' => $permissions,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        // Demo mode restriction
        if (config('app.demo_mode')) {
            return redirect()->back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => [
                Rule::exists('permissions', 'id')->where(fn ($query) => $query
                    ->whereNull('tenant_id')
                    ->where('name', 'like', 'tenant-%')),
            ],
        ]);

        $role->update([
            'display_name' => $validated['display_name'],
            'description' => $validated['description'],
        ]);

        $permissionIds = collect($validated['permission_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $role->permissions()->sync($permissionIds);

        return redirect()
            ->route('admin.roles.index', ['subdomain' => request('subdomain')])
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        // Demo mode restriction
        if (config('app.demo_mode')) {
            return redirect()->back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        // Check if role is in use
        if ($role->users()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete role that is assigned to employees.');
        }

        $role->delete();

        return redirect()
            ->route('admin.roles.index', ['subdomain' => request('subdomain')])
            ->with('success', 'Role deleted successfully.');
    }

    private function tenantId(): int
    {
        return (int) (TenantContext::id() ?? auth()->user()?->tenant_id ?? 0);
    }
}
