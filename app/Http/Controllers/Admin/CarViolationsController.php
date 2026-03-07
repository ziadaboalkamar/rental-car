<?php

namespace App\Http\Controllers\Admin;

use App\Core\TenantContext;
use App\Enums\CarViolationStatus;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarViolation;
use App\Support\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CarViolationsController extends Controller
{
    public function __construct(private BranchAccess $branchAccess)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $carId = $this->branchAccess->normalizeRequestedBranchId($request->input('car_id'));

        $branchOptions = $this->branchAccess
            ->availableBranchesForUser($user)
            ->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])
            ->values();

        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;

        $carsQuery = Car::query()
            ->select(['id', 'make', 'model', 'year', 'license_plate', 'branch_id'])
            ->orderBy('make')
            ->orderBy('model');
        $this->branchAccess->applyToQuery($carsQuery, $user, $branchId);
        $cars = $carsQuery
            ->get()
            ->map(fn (Car $car) => [
                'id' => $car->id,
                'label' => trim("{$car->year} {$car->make} {$car->model} ({$car->license_plate})"),
            ])
            ->values();

        $query = CarViolation::query()->with([
            'car:id,make,model,year,license_plate',
            'branch:id,name',
            'issuedTo:id,name',
        ]);

        $this->branchAccess->applyToQuery($query, $user, $branchId, 'branch_id');

        if ($carId) {
            $query->where('car_id', $carId);
        }

        $query
            ->when($status !== '' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('violation_number', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('authority', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('car', function ($carQuery) use ($search) {
                            $carQuery->where('make', 'like', "%{$search}%")
                                ->orWhere('model', 'like', "%{$search}%")
                                ->orWhere('license_plate', 'like', "%{$search}%");
                        });
                });
            });

        $violations = $query
            ->latest('violation_date')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $violations->getCollection()->transform(function (CarViolation $violation) {
            $statusValue = $violation->status instanceof CarViolationStatus
                ? $violation->status->value
                : (string) $violation->status;
            $statusLabel = $violation->status instanceof CarViolationStatus
                ? $violation->status->label()
                : ucfirst(str_replace('_', ' ', $statusValue));
            $statusColor = $violation->status instanceof CarViolationStatus
                ? $violation->status->color()
                : '#6B7280';

            return [
                'id' => $violation->id,
                'violation_number' => $violation->violation_number,
                'car' => $violation->car
                    ? trim("{$violation->car->year} {$violation->car->make} {$violation->car->model} ({$violation->car->license_plate})")
                    : '-',
                'type' => $violation->type,
                'amount' => (float) $violation->amount,
                'status' => $statusValue,
                'status_label' => $statusLabel,
                'status_color' => $statusColor,
                'violation_date' => optional($violation->violation_date)?->toDateString(),
                'due_date' => optional($violation->due_date)?->toDateString(),
                'branch' => $violation->branch?->name ?? '-',
                'issued_to' => $violation->issuedTo?->name ?? '-',
                'edit_url' => route('admin.car-violations.edit', $violation),
                'destroy_url' => route('admin.car-violations.destroy', $violation),
            ];
        });

        $statuses = collect(CarViolationStatus::cases())->map(fn ($statusCase) => [
            'value' => $statusCase->value,
            'label' => $statusCase->label(),
            'color' => $statusCase->color(),
        ])->values();

        return Inertia::render('Admin/CarViolations/Index', [
            'violations' => $violations,
            'statuses' => $statuses,
            'branches' => $branchOptions,
            'cars' => $cars,
            'canAccessAllBranches' => $canAccessAllBranches,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
                'car_id' => $carId,
            ],
            'indexUrl' => route('admin.car-violations.index'),
            'createUrl' => route('admin.car-violations.create'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/CarViolations/Edit', [
            'violation' => null,
            ...$this->formOptions($request),
            'indexUrl' => route('admin.car-violations.index'),
            'submitUrl' => route('admin.car-violations.store'),
            'method' => 'post',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateViolation($request);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $car = $this->resolveAccessibleCar($request, (int) $validated['car_id']);

        $status = $validated['status'];
        $paidAt = $validated['paid_at'] ?? null;
        if ($status === CarViolationStatus::PAID->value && empty($paidAt)) {
            $paidAt = now()->toDateTimeString();
        }

        CarViolation::create([
            'car_id' => $car->id,
            'branch_id' => $car->branch_id,
            'reservation_id' => $validated['reservation_id'] ?? null,
            'issued_to_user_id' => $validated['issued_to_user_id'] ?? null,
            'created_by' => $request->user()?->id,
            'violation_number' => $validated['violation_number'] ?? null,
            'violation_date' => $validated['violation_date'],
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'status' => $status,
            'due_date' => $validated['due_date'] ?? null,
            'paid_at' => $paidAt,
            'payment_reference' => $validated['payment_reference'] ?? null,
            'authority' => $validated['authority'] ?? null,
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.car-violations.index')
            ->with('success', 'Car violation created successfully.');
    }

    public function edit(Request $request, CarViolation $carViolation): Response
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $carViolation->branch_id ? (int) $carViolation->branch_id : null), 403);

        return Inertia::render('Admin/CarViolations/Edit', [
            'violation' => [
                'id' => $carViolation->id,
                'car_id' => $carViolation->car_id,
                'reservation_id' => $carViolation->reservation_id,
                'issued_to_user_id' => $carViolation->issued_to_user_id,
                'violation_number' => $carViolation->violation_number,
                'violation_date' => optional($carViolation->violation_date)?->toDateString(),
                'type' => $carViolation->type,
                'amount' => (float) $carViolation->amount,
                'status' => $carViolation->status instanceof CarViolationStatus ? $carViolation->status->value : (string) $carViolation->status,
                'due_date' => optional($carViolation->due_date)?->toDateString(),
                'paid_at' => optional($carViolation->paid_at)?->format('Y-m-d\TH:i'),
                'payment_reference' => $carViolation->payment_reference,
                'authority' => $carViolation->authority,
                'location' => $carViolation->location,
                'description' => $carViolation->description,
                'notes' => $carViolation->notes,
            ],
            ...$this->formOptions($request),
            'indexUrl' => route('admin.car-violations.index'),
            'submitUrl' => route('admin.car-violations.update', $carViolation),
            'method' => 'put',
        ]);
    }

    public function update(Request $request, CarViolation $carViolation): RedirectResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $carViolation->branch_id ? (int) $carViolation->branch_id : null), 403);

        $validated = $this->validateViolation($request, $carViolation);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $car = $this->resolveAccessibleCar($request, (int) $validated['car_id']);

        $status = $validated['status'];
        $paidAt = $validated['paid_at'] ?? null;
        if ($status === CarViolationStatus::PAID->value && empty($paidAt)) {
            $paidAt = now()->toDateTimeString();
        }
        if ($status !== CarViolationStatus::PAID->value) {
            $paidAt = null;
        }

        $carViolation->update([
            'car_id' => $car->id,
            'branch_id' => $car->branch_id,
            'reservation_id' => $validated['reservation_id'] ?? null,
            'issued_to_user_id' => $validated['issued_to_user_id'] ?? null,
            'violation_number' => $validated['violation_number'] ?? null,
            'violation_date' => $validated['violation_date'],
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'status' => $status,
            'due_date' => $validated['due_date'] ?? null,
            'paid_at' => $paidAt,
            'payment_reference' => $validated['payment_reference'] ?? null,
            'authority' => $validated['authority'] ?? null,
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.car-violations.index')
            ->with('success', 'Car violation updated successfully.');
    }

    public function destroy(Request $request, CarViolation $carViolation): RedirectResponse
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $carViolation->branch_id ? (int) $carViolation->branch_id : null), 403);

        if (config('app.demo_mode')) {
            return back()->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $carViolation->delete();

        return back()->with('success', 'Car violation deleted successfully.');
    }

    private function validateViolation(Request $request, ?CarViolation $carViolation = null): array
    {
        $tenantId = (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0);

        return $request->validate([
            'car_id' => ['required', 'integer', Rule::exists('cars', 'id')],
            'reservation_id' => [
                'nullable',
                'integer',
                Rule::exists('reservations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'issued_to_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'violation_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('car_violations', 'violation_number')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($carViolation?->id),
            ],
            'violation_date' => ['required', 'date'],
            'type' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'string', Rule::enum(CarViolationStatus::class)],
            'due_date' => ['nullable', 'date'],
            'paid_at' => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'authority' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function resolveAccessibleCar(Request $request, int $carId): Car
    {
        $query = Car::query()->whereKey($carId);
        $this->branchAccess->applyToQuery($query, $request->user(), null);
        $car = $query->first();

        abort_if(!$car, 422, 'Selected car is not accessible.');

        return $car;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $tenantId = (int) (TenantContext::id() ?? $request->user()?->tenant_id ?? 0);
        $user = $request->user();

        $carsQuery = Car::query()->select(['id', 'year', 'make', 'model', 'license_plate']);
        $this->branchAccess->applyToQuery($carsQuery, $user, null);
        $cars = $carsQuery
            ->orderBy('make')
            ->orderBy('model')
            ->get()
            ->map(fn (Car $car) => [
                'id' => $car->id,
                'label' => trim("{$car->year} {$car->make} {$car->model} ({$car->license_plate})"),
            ])
            ->values();

        $clients = \App\Models\User::query()
            ->select(['id', 'name', 'email'])
            ->where('tenant_id', $tenantId)
            ->where('role', 'client')
            ->orderBy('name')
            ->get()
            ->map(fn (\App\Models\User $client) => [
                'id' => $client->id,
                'label' => trim($client->name.' ('.$client->email.')'),
            ])
            ->values();

        $reservations = \App\Models\Reservation::query()
            ->select(['id', 'reservation_number', 'car_id'])
            ->where('tenant_id', $tenantId)
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (\App\Models\Reservation $reservation) => [
                'id' => $reservation->id,
                'label' => $reservation->reservation_number ?: ('Reservation #'.$reservation->id),
                'car_id' => $reservation->car_id,
            ])
            ->values();

        $statuses = collect(CarViolationStatus::cases())->map(fn ($statusCase) => [
            'value' => $statusCase->value,
            'label' => $statusCase->label(),
            'color' => $statusCase->color(),
        ])->values();

        return [
            'cars' => $cars,
            'clients' => $clients,
            'reservations' => $reservations,
            'statuses' => $statuses,
        ];
    }
}

