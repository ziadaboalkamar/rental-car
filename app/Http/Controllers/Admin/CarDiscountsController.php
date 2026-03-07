<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarDiscount;
use App\Support\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CarDiscountsController extends Controller
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

        $query = CarDiscount::query()->with('car:id,make,model,year,license_plate');
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
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $discounts = $query->latest()->paginate(12)->withQueryString();
        $discounts->getCollection()->transform(function (CarDiscount $discount) {
            return [
                'id' => $discount->id,
                'name' => $discount->name,
                'car' => $discount->car
                    ? trim("{$discount->car->year} {$discount->car->make} {$discount->car->model}")
                    : 'All cars',
                'type' => $discount->type?->value ?? (string) $discount->type,
                'value' => (float) $discount->value,
                'priority' => (int) $discount->priority,
                'is_active' => (bool) $discount->is_active,
                'starts_at' => optional($discount->starts_at)?->toDateString(),
                'ends_at' => optional($discount->ends_at)?->toDateString(),
                'edit_url' => route('admin.car-discounts.edit', $discount),
                'delete_url' => route('admin.car-discounts.destroy', $discount),
            ];
        });

        return Inertia::render('Admin/CarDiscounts/Index', [
            'discounts' => $discounts,
            'cars' => $cars,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'car_id' => $carId,
            ],
            'indexUrl' => route('admin.car-discounts.index'),
            'createUrl' => route('admin.car-discounts.create'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/CarDiscounts/Edit', [
            'discount' => null,
            ...$this->formOptions($request),
            'indexUrl' => route('admin.car-discounts.index'),
            'submitUrl' => route('admin.car-discounts.store'),
            'method' => 'post',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $validated = $this->validateDiscount($request);
        if ($validated['type'] === CouponType::PERCENTAGE->value && (float) $validated['value'] > 100) {
            return back()->withInput()->withErrors(['value' => 'Percentage value cannot exceed 100.']);
        }

        $carId = $validated['car_id'] ? (int) $validated['car_id'] : null;
        if ($carId) {
            $this->resolveAccessibleCar($request, $carId);
        }

        CarDiscount::create([
            'tenant_id' => TenantContext::id() ?? $request->user()?->tenant_id,
            'car_id' => $carId,
            'created_by' => $request->user()?->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'min_total_amount' => $validated['min_total_amount'] ?? null,
            'min_days' => $validated['min_days'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'priority' => (int) ($validated['priority'] ?? 0),
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()->route('admin.car-discounts.index')->with('success', 'Automatic discount created successfully.');
    }

    public function edit(Request $request, CarDiscount $carDiscount): Response
    {
        abort_if((int) $carDiscount->tenant_id !== (int) (TenantContext::id() ?? $request->user()?->tenant_id), 404);
        if ($carDiscount->car_id) {
            abort_unless($this->branchAccess->canAccessBranchId($request->user(), $carDiscount->car?->branch_id ? (int) $carDiscount->car->branch_id : null), 403);
        }

        return Inertia::render('Admin/CarDiscounts/Edit', [
            'discount' => [
                'id' => $carDiscount->id,
                'car_id' => $carDiscount->car_id,
                'name' => $carDiscount->name,
                'description' => $carDiscount->description,
                'type' => $carDiscount->type?->value ?? (string) $carDiscount->type,
                'value' => (float) $carDiscount->value,
                'max_discount_amount' => $carDiscount->max_discount_amount !== null ? (float) $carDiscount->max_discount_amount : null,
                'min_total_amount' => $carDiscount->min_total_amount !== null ? (float) $carDiscount->min_total_amount : null,
                'min_days' => $carDiscount->min_days,
                'starts_at' => optional($carDiscount->starts_at)?->format('Y-m-d\TH:i'),
                'ends_at' => optional($carDiscount->ends_at)?->format('Y-m-d\TH:i'),
                'priority' => (int) $carDiscount->priority,
                'is_active' => (bool) $carDiscount->is_active,
            ],
            ...$this->formOptions($request),
            'indexUrl' => route('admin.car-discounts.index'),
            'submitUrl' => route('admin.car-discounts.update', $carDiscount),
            'method' => 'put',
        ]);
    }

    public function update(Request $request, CarDiscount $carDiscount): RedirectResponse
    {
        abort_if((int) $carDiscount->tenant_id !== (int) (TenantContext::id() ?? $request->user()?->tenant_id), 404);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $validated = $this->validateDiscount($request);
        if ($validated['type'] === CouponType::PERCENTAGE->value && (float) $validated['value'] > 100) {
            return back()->withInput()->withErrors(['value' => 'Percentage value cannot exceed 100.']);
        }

        $carId = $validated['car_id'] ? (int) $validated['car_id'] : null;
        if ($carId) {
            $this->resolveAccessibleCar($request, $carId);
        }

        $carDiscount->update([
            'car_id' => $carId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'min_total_amount' => $validated['min_total_amount'] ?? null,
            'min_days' => $validated['min_days'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'priority' => (int) ($validated['priority'] ?? 0),
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()->route('admin.car-discounts.index')->with('success', 'Automatic discount updated successfully.');
    }

    public function destroy(Request $request, CarDiscount $carDiscount): RedirectResponse
    {
        abort_if((int) $carDiscount->tenant_id !== (int) (TenantContext::id() ?? $request->user()?->tenant_id), 404);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $carDiscount->delete();

        return back()->with('success', 'Automatic discount deleted successfully.');
    }

    private function validateDiscount(Request $request): array
    {
        return $request->validate([
            'car_id' => ['nullable', 'integer', Rule::exists('cars', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'numeric', 'min:0.01'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0.01'],
            'min_total_amount' => ['nullable', 'numeric', 'min:0'],
            'min_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
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

