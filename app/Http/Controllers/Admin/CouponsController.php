<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Coupon;
use App\Support\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CouponsController extends Controller
{
    public function __construct(private BranchAccess $branchAccess)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', 'all'));
        $carId = $this->branchAccess->normalizeRequestedBranchId($request->query('car_id'));

        $carsQuery = Car::query()->select(['id', 'make', 'model', 'year', 'license_plate'])->orderBy('make')->orderBy('model');
        $this->branchAccess->applyToQuery($carsQuery, $user, null);
        $cars = $carsQuery->get()->map(fn (Car $car) => [
            'id' => $car->id,
            'label' => trim("{$car->year} {$car->make} {$car->model} ({$car->license_plate})"),
        ])->values();

        $query = Coupon::query()->with('car:id,make,model,year,license_plate');

        if ($carId) {
            $query->where('car_id', $carId);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $coupons = $query->latest()->paginate(12)->withQueryString();
        $coupons->getCollection()->transform(function (Coupon $coupon) {
            return [
                'id' => $coupon->id,
                'name' => $coupon->name,
                'code' => $coupon->code,
                'car' => $coupon->car
                    ? trim("{$coupon->car->year} {$coupon->car->make} {$coupon->car->model}")
                    : 'All cars',
                'type' => $coupon->type?->value ?? (string) $coupon->type,
                'value' => (float) $coupon->value,
                'is_active' => (bool) $coupon->is_active,
                'usage_limit' => $coupon->usage_limit,
                'used_count' => $coupon->used_count,
                'starts_at' => optional($coupon->starts_at)?->toDateString(),
                'ends_at' => optional($coupon->ends_at)?->toDateString(),
                'edit_url' => route('admin.coupons.edit', $coupon),
                'delete_url' => route('admin.coupons.destroy', $coupon),
            ];
        });

        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => $coupons,
            'cars' => $cars,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'car_id' => $carId,
            ],
            'indexUrl' => route('admin.coupons.index'),
            'createUrl' => route('admin.coupons.create'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Coupons/Edit', [
            'coupon' => null,
            ...$this->formOptions($request),
            'indexUrl' => route('admin.coupons.index'),
            'submitUrl' => route('admin.coupons.store'),
            'method' => 'post',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $validated = $this->validateCoupon($request);
        if ($validated['type'] === CouponType::PERCENTAGE->value && (float) $validated['value'] > 100) {
            return back()->withInput()->withErrors(['value' => 'Percentage value cannot exceed 100.']);
        }
        $code = strtoupper(trim((string) $validated['code']));
        $carId = $validated['car_id'] ? (int) $validated['car_id'] : null;

        if ($carId) {
            $this->resolveAccessibleCar($request, $carId);
        }

        Coupon::create([
            'tenant_id' => TenantContext::id() ?? $request->user()?->tenant_id,
            'car_id' => $carId,
            'created_by' => $request->user()?->id,
            'name' => $validated['name'],
            'code' => $code,
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'min_total_amount' => $validated['min_total_amount'] ?? null,
            'min_days' => $validated['min_days'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created successfully.');
    }

    public function edit(Request $request, Coupon $coupon): Response
    {
        abort_if((int) $coupon->tenant_id !== (int) (TenantContext::id() ?? $request->user()?->tenant_id), 404);
        if ($coupon->car_id) {
            abort_unless($this->branchAccess->canAccessBranchId($request->user(), $coupon->car?->branch_id ? (int) $coupon->car->branch_id : null), 403);
        }

        return Inertia::render('Admin/Coupons/Edit', [
            'coupon' => [
                'id' => $coupon->id,
                'car_id' => $coupon->car_id,
                'name' => $coupon->name,
                'code' => $coupon->code,
                'description' => $coupon->description,
                'type' => $coupon->type?->value ?? (string) $coupon->type,
                'value' => (float) $coupon->value,
                'max_discount_amount' => $coupon->max_discount_amount !== null ? (float) $coupon->max_discount_amount : null,
                'min_total_amount' => $coupon->min_total_amount !== null ? (float) $coupon->min_total_amount : null,
                'min_days' => $coupon->min_days,
                'starts_at' => optional($coupon->starts_at)?->format('Y-m-d\TH:i'),
                'ends_at' => optional($coupon->ends_at)?->format('Y-m-d\TH:i'),
                'usage_limit' => $coupon->usage_limit,
                'is_active' => (bool) $coupon->is_active,
            ],
            ...$this->formOptions($request),
            'indexUrl' => route('admin.coupons.index'),
            'submitUrl' => route('admin.coupons.update', $coupon),
            'method' => 'put',
        ]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        abort_if((int) $coupon->tenant_id !== (int) (TenantContext::id() ?? $request->user()?->tenant_id), 404);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $validated = $this->validateCoupon($request, $coupon);
        if ($validated['type'] === CouponType::PERCENTAGE->value && (float) $validated['value'] > 100) {
            return back()->withInput()->withErrors(['value' => 'Percentage value cannot exceed 100.']);
        }
        $code = strtoupper(trim((string) $validated['code']));
        $carId = $validated['car_id'] ? (int) $validated['car_id'] : null;

        if ($carId) {
            $this->resolveAccessibleCar($request, $carId);
        }

        $coupon->update([
            'car_id' => $carId,
            'name' => $validated['name'],
            'code' => $code,
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'min_total_amount' => $validated['min_total_amount'] ?? null,
            'min_days' => $validated['min_days'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon updated successfully.');
    }

    public function destroy(Request $request, Coupon $coupon): RedirectResponse
    {
        abort_if((int) $coupon->tenant_id !== (int) (TenantContext::id() ?? $request->user()?->tenant_id), 404);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $coupon->delete();

        return back()->with('success', 'Coupon deleted successfully.');
    }

    private function validateCoupon(Request $request, ?Coupon $coupon = null): array
    {
        $tenantId = (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0);
        $couponId = $coupon?->id;

        return $request->validate([
            'car_id' => ['nullable', 'integer', Rule::exists('cars', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('coupons', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($couponId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'numeric', 'min:0.01'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0.01'],
            'min_total_amount' => ['nullable', 'numeric', 'min:0'],
            'min_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ], [
            'code.unique' => 'Coupon code already exists in this tenant.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $carsQuery = Car::query()->select(['id', 'make', 'model', 'year', 'license_plate'])->orderBy('make')->orderBy('model');
        $this->branchAccess->applyToQuery($carsQuery, $request->user(), null);
        $cars = $carsQuery->get()->map(fn (Car $car) => [
            'id' => $car->id,
            'label' => trim("{$car->year} {$car->make} {$car->model} ({$car->license_plate})"),
        ])->values();

        $types = collect(CouponType::cases())->map(fn (CouponType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ])->values();

        return [
            'cars' => $cars,
            'types' => $types,
        ];
    }

    private function resolveAccessibleCar(Request $request, int $carId): Car
    {
        $query = Car::query()->whereKey($carId);
        $this->branchAccess->applyToQuery($query, $request->user(), null);
        $car = $query->first();

        abort_if(!$car, 422, 'Selected car is not accessible.');

        return $car;
    }
}
