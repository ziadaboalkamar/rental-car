<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\CarColor;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Models\Branch;
use App\Models\Car;
use App\Support\BranchAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;
use Illuminate\Support\Facades\Storage;

class CarsController extends Controller
{
    protected FilePondService $filePondService;
    protected BranchAccess $branchAccess;

    public function __construct(FilePondService $filePondService, BranchAccess $branchAccess)
    {
        $this->filePondService = $filePondService;
        $this->branchAccess = $branchAccess;
    }

    /**
     * Display a listing of cars.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        $status = $request->input('status');
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

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
        
        // Get status counts for the filter
        $statusCountsQuery = Car::query()->selectRaw('status, count(*) as count');
        $this->branchAccess->applyToQuery($statusCountsQuery, $user, $branchId);
        $statusCounts = $statusCountsQuery
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $carsQuery = Car::query()
            ->with('files')
            ->with('branch:id,name');

        $this->branchAccess->applyToQuery($carsQuery, $user, $branchId);

        $cars = $carsQuery
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('make', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('license_plate', 'like', "%{$search}%");
                });
            })
            ->when($status && $status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $cars->getCollection()->transform(function (Car $car) {
            $status = $car->status;

            return [
                'id' => $car->id,
                'make' => $car->make,
                'model' => $car->model,
                'year' => $car->year,
                'license_plate' => $car->license_plate,
                'price_per_day' => $car->price_per_day,
                'status' => $status instanceof CarStatus ? $status->value : (string) $status,
                'status_label' => $status instanceof CarStatus ? $status->label() : null,
                'status_color' => $status instanceof CarStatus ? $status->color() : null,
                'image_url' => $car->image_url,
                'branch_id' => $car->branch_id,
                'branch_name' => $car->branch?->name,
            ];
        });

        $statuses = collect(CarStatus::cases())->mapWithKeys(function ($status) use ($statusCounts) {
            return [
                $status->value => [
                    'label' => $status->label(),
                    'count' => $statusCounts[$status->value] ?? 0,
                    'color' => $status->color(),
                ]
            ];
        })->toArray();

        return Inertia::render('Admin/Cars/Index', [
            'cars' => $cars,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $status,
                'branch_id' => $branchId,
            ],
            'statuses' => $statuses,
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
        ]);
    }

    /**
     * Show the form for creating a new car.
     */
    public function create(): Response
    {
        $user = request()->user();

        return Inertia::render('Admin/Cars/Edit', [
            'car' => null,
            'imageFiles' => [],
            'branches' => $this->branchAccess->availableBranchesForUser($user)->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])->values(),
            'canAccessAllBranches' => $this->branchAccess->canAccessAllBranches($user),
            'enums' => [
                'colors' => CarColor::forFrontend(),
                'fuelTypes' => FuelType::forFrontend(),
                'statuses' => array_map(fn($status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'color' => $status->color()
                ], CarStatus::cases()),
            ],
        ]);
    }

    /**
     * Store a newly created car in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $tenantId = (int) ($user?->tenant_id ?? 0);
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        $validated = $request->validate([
            'make' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'license_plate' => ['required', 'string', 'max:255', 'unique:cars,license_plate'],
            'branch_id' => [
                $canAccessAllBranches ? 'required' : 'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'color' => ['required', 'string', Rule::enum(CarColor::class)],
            'price_per_day' => ['required', 'numeric', 'min:0'],
            'mileage' => ['required', 'integer', 'min:0'],
            'transmission' => ['required', Rule::in(['automatic', 'manual'])],
            'seats' => ['required', 'integer', 'min:1'],
            'fuel_type' => ['required', 'string', Rule::enum(FuelType::class)],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::enum(CarStatus::class)],
            'image' => ['array'],
            'image.*' => ['string'],
        ]);

        $validated['branch_id'] = $this->branchAccess->resolveWritableBranchId(
            $user,
            $this->branchAccess->normalizeRequestedBranchId($validated['branch_id'] ?? null)
        );

        if (!$validated['branch_id']) {
            return back()->withErrors([
                'branch_id' => 'A branch is required to create a car.',
            ])->withInput();
        }

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $car = Car::create(collect($validated)->except(['image'])->toArray());

        // Handle single image upload if provided
        if ($request->filled('image')) {
            $this->filePondService->handleFileUploads(
                $car,
                $request->input('image', []),
                'image'
            );
        }

        return redirect()
            ->route('admin.cars.index')
            ->with('success', 'Car created successfully.');
    }

    /**
     * Show the form for editing the specified car.
     */
    public function edit(Car $car): Response
    {
        abort_unless($this->branchAccess->canAccessBranchId(request()->user(), $car->branch_id), 403);

        // Provide initial image files for FilePond (only the 'image' collection)
        $disk = config('vilt-filepond.storage_disk', 'public');
        $imageFiles = $car->files()
            ->where('collection', 'image')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'url' => Storage::url($f->path),
            ]);

        return Inertia::render('Admin/Cars/Edit', [
            'car' => $car,
            'imageFiles' => $imageFiles,
            'branches' => $this->branchAccess->availableBranchesForUser(request()->user())->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])->values(),
            'canAccessAllBranches' => $this->branchAccess->canAccessAllBranches(request()->user()),
            'enums' => [
                'colors' => CarColor::forFrontend(),
                'fuelTypes' => FuelType::forFrontend(),
                'statuses' => array_map(fn($status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'color' => $status->color()
                ], CarStatus::cases()),
            ],
        ]);
    }

    /**
     * Update the specified car in storage.
     */
    public function update(Request $request, Car $car)
    {
        abort_unless($this->branchAccess->canAccessBranchId($request->user(), $car->branch_id), 403);

        $user = $request->user();
        $tenantId = (int) ($user?->tenant_id ?? 0);
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        $validated = $request->validate([
            'make' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'branch_id' => [
                $canAccessAllBranches ? 'required' : 'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'license_plate' => [
                'required', 'string', 'max:255', Rule::unique('cars', 'license_plate')->ignore($car->id),
            ],
            'color' => ['required', 'string', Rule::enum(CarColor::class)],
            'price_per_day' => ['required', 'numeric', 'min:0'],
            'mileage' => ['required', 'integer', 'min:0'],
            'transmission' => ['required', Rule::in(['automatic', 'manual'])],
            'seats' => ['required', 'integer', 'min:1'],
            'fuel_type' => ['required', 'string', Rule::enum(FuelType::class)],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::enum(CarStatus::class)],
            // File updates for single image
            'image_temp_folders' => ['array'],
            'image_temp_folders.*' => ['string'],
            'image_removed_files' => ['array'],
            'image_removed_files.*' => ['integer'],
        ]);

        $validated['branch_id'] = $this->branchAccess->resolveWritableBranchId(
            $user,
            $this->branchAccess->normalizeRequestedBranchId($validated['branch_id'] ?? null)
        );

        if (!$validated['branch_id']) {
            return back()->withErrors([
                'branch_id' => 'A branch is required to update a car.',
            ])->withInput();
        }

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }


        $car->update(collect($validated)->except(['image_temp_folders', 'image_removed_files'])->toArray());

        // Ensure single-image semantics: if new temp folders exist, remove any existing 'image' files
        $tempFolders = $request->input('image_temp_folders', []);
        $removedIds = $request->input('image_removed_files', []);
        if (!empty($tempFolders)) {
            $existingIds = $car->files()->where('collection', 'image')->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
        }

        // Handle file updates for the single image collection
        $this->filePondService->handleFileUpdates(
            $car,
            $tempFolders,
            $removedIds,
            'image'
        );

        return redirect()
            ->route('admin.cars.index')
            ->with('success', 'Car updated successfully.');
    }

    /**
     * Remove the specified car from storage.
     */
    public function destroy(Car $car)
    {
        abort_unless($this->branchAccess->canAccessBranchId(request()->user(), $car->branch_id), 403);

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $car->delete();

        return redirect()
            ->back()
            ->with('success', 'Car deleted successfully.');
    }
}
