<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceTypesController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $maintenanceTypes = MaintenanceType::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $maintenanceTypes->getCollection()->transform(function (MaintenanceType $type) {
            return [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'is_active' => (bool) $type->is_active,
                'sort_order' => (int) $type->sort_order,
                'edit_url' => route('admin.maintenance-types.edit', $type),
                'destroy_url' => route('admin.maintenance-types.destroy', $type),
            ];
        });

        return Inertia::render('Admin/MaintenanceTypes/Index', [
            'maintenanceTypes' => $maintenanceTypes,
            'filters' => [
                'search' => $search,
            ],
            'indexUrl' => route('admin.maintenance-types.index'),
            'createUrl' => route('admin.maintenance-types.create'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/MaintenanceTypes/Edit', [
            'maintenanceType' => null,
            'indexUrl' => route('admin.maintenance-types.index'),
            'submitUrl' => route('admin.maintenance-types.store'),
            'method' => 'post',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('maintenance_types', 'name')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        MaintenanceType::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return redirect()
            ->route('admin.maintenance-types.index')
            ->with('success', 'Maintenance type created successfully.');
    }

    public function edit(MaintenanceType $maintenanceType): Response
    {
        return Inertia::render('Admin/MaintenanceTypes/Edit', [
            'maintenanceType' => [
                'id' => $maintenanceType->id,
                'name' => $maintenanceType->name,
                'description' => $maintenanceType->description,
                'is_active' => (bool) $maintenanceType->is_active,
                'sort_order' => (int) $maintenanceType->sort_order,
            ],
            'indexUrl' => route('admin.maintenance-types.index'),
            'submitUrl' => route('admin.maintenance-types.update', $maintenanceType),
            'method' => 'put',
        ]);
    }

    public function update(Request $request, MaintenanceType $maintenanceType): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('maintenance_types', 'name')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($maintenanceType->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $maintenanceType->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return redirect()
            ->route('admin.maintenance-types.index')
            ->with('success', 'Maintenance type updated successfully.');
    }

    public function destroy(MaintenanceType $maintenanceType): RedirectResponse
    {
        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $maintenanceType->delete();

        return back()->with('success', 'Maintenance type deleted successfully.');
    }

    private function tenantId(Request $request): int
    {
        return (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0);
    }
}

