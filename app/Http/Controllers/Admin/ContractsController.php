<?php

namespace App\Http\Controllers\Admin;

use App\Core\AiAutomationSettings;
use App\Core\AiProviderSettings;
use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarDamageCase;
use App\Models\Contract;
use App\Models\ContractDriver;
use App\Models\ContractDriverDocument;
use App\Models\Reservation;
use App\Models\TenantSiteSetting;
use App\Models\User;
use App\Services\Contracts\ContractAiExtractor;
use App\Services\Contracts\ContractDriverDocumentExtractor;
use App\Support\BranchAccess;
use App\Support\CarDamageCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use MohamedGaldi\ViltFilepond\Models\TempFile;
use MohamedGaldi\ViltFilepond\Services\FilePondService;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

class ContractsController extends Controller
{
    public function __construct(
        private BranchAccess $branchAccess,
        private FilePondService $filePondService,
        private ContractAiExtractor $contractAiExtractor,
        private ContractDriverDocumentExtractor $contractDriverDocumentExtractor
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);
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

        $contractsQuery = Contract::query()
            ->with([
                'reservation:id,reservation_number,user_id,car_id',
                'reservation.user:id,name,email',
                'reservation.car:id,make,model,year,license_plate',
                'branch:id,name',
            ])
            ->withCount([
                'files as start_contract_count' => fn ($q) => $q->where('collection', 'start_contract'),
                'files as end_contract_count' => fn ($q) => $q->where('collection', 'end_contract'),
            ]);

        $this->applyContractBranchScope($contractsQuery, $user, $branchId);

        $contracts = $contractsQuery
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('contract_number', 'like', "%{$search}%")
                        ->orWhere('renter_name', 'like', "%{$search}%")
                        ->orWhereHas('reservation', function ($rq) use ($search) {
                            $rq->where('reservation_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '' && $status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $contracts->getCollection()->transform(function (Contract $contract) {
            return [
                'id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'status' => $contract->status,
                'reservation_number' => $contract->reservation?->reservation_number,
                'renter_name' => $contract->renter_name,
                'branch_name' => $contract->branch?->name,
                'start_date' => optional($contract->start_date)->toDateString(),
                'end_date' => optional($contract->end_date)->toDateString(),
                'total_amount' => $contract->total_amount,
                'currency' => $contract->currency,
                'has_start_contract' => (int) $contract->start_contract_count > 0,
                'has_end_contract' => (int) $contract->end_contract_count > 0,
            ];
        });

        return Inertia::render('Admin/Contracts/Index', [
            'contracts' => $contracts,
            'filters' => [
                'search' => $search,
                'status' => $status === '' ? 'all' : $status,
                'branch_id' => $branchId,
            ],
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
            'statuses' => ['draft', 'active', 'completed', 'cancelled'],
            'actions' => [
                'index' => route('admin.contracts.index', ['subdomain' => $request->route('subdomain')]),
                'create' => route('admin.contracts.create', ['subdomain' => $request->route('subdomain')]),
                'extract' => route('admin.contracts.extract', ['subdomain' => $request->route('subdomain')]),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $reservation = null;
        if ($request->filled('reservation_id')) {
            $reservation = Reservation::query()
                ->with(['user:id,name,email', 'car:id,branch_id,make,model,year,license_plate'])
                ->find($request->integer('reservation_id'));
            if ($reservation) {
                $this->ensureReservationAccessible($reservation, $request->user(), null);
            }
        }

        $reservations = $this->reservationOptions($request);
        $carDamageMap = $this->serializeCarDamageCaseMap(
            collect($reservations)
                ->pluck('car_id')
                ->push($reservation?->car_id)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
            $request->user()
        );

        return Inertia::render('Admin/Contracts/Edit', [
            'mode' => 'create',
            'contract' => $reservation ? $this->prefillFromReservation($reservation) : [
                'contract_number' => $this->generateContractNumber(),
                'status' => 'draft',
                'contract_date' => now()->toDateString(),
                'currency' => strtoupper((string) config('app.currency_code', 'USD')),
            ],
            'carData' => $reservation ? $this->prefillCarDataFromReservation($reservation) : $this->emptyCarData(),
            'currentCarDamages' => $reservation?->car_id ? $this->serializeCarDamageCases((int) $reservation->car_id, $request->user()) : [],
            'carDamagesByCar' => $carDamageMap,
            'primaryDriver' => $reservation ? $this->prefillPrimaryDriverFromReservation($reservation) : $this->emptyDriverPayload('primary'),
            'additionalDrivers' => [],
            'contractArchive' => $this->emptyContractArchivePayload(),
            'reservationOptions' => $reservations,
            'reservationFormOptions' => [
                'clients' => $this->reservationClientOptions($request),
                'cars' => $this->reservationCarOptions($request),
            ],
            'startContractFiles' => [],
            'endContractFiles' => [],
            'ai' => [
                'contracts_extraction_enabled' => AiAutomationSettings::isContractsExtractionEnabled(),
            ],
            'actions' => [
                'index' => route('admin.contracts.index', ['subdomain' => $request->route('subdomain')]),
                'store' => route('admin.contracts.store', ['subdomain' => $request->route('subdomain')]),
                'extract' => route('admin.contracts.extract', ['subdomain' => $request->route('subdomain')]),
                'extractDriver' => route('admin.contracts.drivers.extract', ['subdomain' => $request->route('subdomain')]),
                'reservationStore' => route('admin.reservations.store', ['subdomain' => $request->route('subdomain')]),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = (int) (TenantContext::id() ?? 0);
        if ($tenantId <= 0) {
            abort(404);
        }

        $validated = $this->validatePayload($request, $tenantId);
        $reservation = $this->validatedReservation($validated['reservation_id'] ?? null, $request, null);

        $contract = DB::transaction(function () use ($request, $tenantId, $validated, $reservation) {
            $contract = new Contract();
            $contract->tenant_id = $tenantId;
            $contract->branch_id = $reservation?->car?->branch_id ? (int) $reservation->car->branch_id : null;
            if (!$contract->branch_id) {
                $contract->branch_id = $this->resolveContractBranchId($validated, $request);
            }
            $contract->reservation_id = $reservation?->id;
            $this->fillContract($contract, $validated, $reservation);
            $this->syncAiStatus($contract, $request, true);
            $contract->save();

            $this->syncFiles($contract, $request, 'start_contract');
            $this->syncFiles($contract, $request, 'end_contract');
            $this->syncContractDrivers($contract, $validated, $reservation);

            return $contract;
        });

        return redirect()
            ->route('admin.contracts.show', [
                'subdomain' => $request->route('subdomain'),
                'contract' => $contract->id,
            ])
            ->with('success', 'Contract created successfully.');
    }

    public function show(Contract $contract): Response
    {
        abort_unless($this->canAccessContract($contract, request()->user()), 403);
        $contract->loadMissing([
            'reservation.user:id,name,email',
            'reservation.car:id,make,model,year,license_plate',
            'branch:id,name',
            'files',
            'primaryDriver.documents',
            'additionalDrivers.documents',
            'damageReports.items',
        ]);

        $damageCreateUrl = route('admin.car-damage-reports.create', array_filter([
            'subdomain' => request()->route('subdomain'),
            'contract_id' => $contract->id,
        ], static fn ($value) => $value !== null && $value !== ''));

        return Inertia::render('Admin/Contracts/Show', [
            'contract' => [
                'id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'status' => $contract->status,
                'contract_date' => optional($contract->contract_date)->toDateString(),
                'renter_name' => $contract->renter_name,
                'renter_id_number' => $contract->renter_id_number,
                'renter_phone' => $contract->renter_phone,
                'car_details' => $contract->car_details,
                'plate_number' => $contract->plate_number,
                'start_date' => optional($contract->start_date)->toDateString(),
                'end_date' => optional($contract->end_date)->toDateString(),
                'total_amount' => $contract->total_amount,
                'currency' => $contract->currency,
                'notes' => $contract->notes,
                'ai_extraction_status' => $contract->ai_extraction_status,
                'ai_extracted_data' => $contract->ai_extracted_data,
                'reservation' => $contract->reservation ? [
                    'id' => $contract->reservation->id,
                    'reservation_number' => $contract->reservation->reservation_number,
                    'user_name' => $contract->reservation->user?->name,
                    'car' => $contract->reservation->car
                        ? "{$contract->reservation->car->year} {$contract->reservation->car->make} {$contract->reservation->car->model}"
                        : null,
                ] : null,
                'branch_name' => $contract->branch?->name,
                'current_damage_cases' => $contract->reservation?->car?->id
                    ? $this->serializeCarDamageCases((int) $contract->reservation->car->id, request()->user())
                    : [],
                'primary_driver' => $contract->primaryDriver ? $this->serializeDriver($contract->primaryDriver) : null,
                'additional_drivers' => $contract->additionalDrivers->map(fn (ContractDriver $driver) => $this->serializeDriver($driver))->values()->all(),
                'damage_reports' => $contract->damageReports->map(function (\App\Models\CarDamageReport $report) {
                    return [
                        'id' => $report->id,
                        'report_number' => $report->report_number,
                        'report_type' => $report->report_type,
                        'report_type_label' => collect(CarDamageCatalog::reportTypes())->firstWhere('value', $report->report_type)['label']
                            ?? Str::title(str_replace('_', ' ', (string) $report->report_type)),
                        'status' => $report->status,
                        'inspected_at' => optional($report->inspected_at)?->format('Y-m-d H:i'),
                        'items_count' => $report->items->count(),
                        'total_quantity' => (int) $report->items->sum('quantity'),
                        'items' => $report->items->map(function ($item) {
                            return [
                                'zone_code' => $item->zone_code,
                                'zone_label' => CarDamageCatalog::zoneLabelMap()[$item->zone_code] ?? $item->zone_code,
                                'damage_type' => $item->damage_type,
                                'severity' => $item->severity,
                                'quantity' => (int) $item->quantity,
                                'notes' => $item->notes,
                            ];
                        })->values()->all(),
                        'edit_url' => route('admin.car-damage-reports.edit', $report),
                    ];
                })->values()->all(),
            ],
            'startRentalDocument' => $this->firstFileMeta($contract, 'start_contract'),
            'endRentalDocument' => $this->firstFileMeta($contract, 'end_contract'),
            'actions' => [
                'index' => route('admin.contracts.index', ['subdomain' => request()->route('subdomain')]),
                'edit' => route('admin.contracts.edit', ['subdomain' => request()->route('subdomain'), 'contract' => $contract->id]),
                'damage_create' => $damageCreateUrl,
                'pdf' => route('admin.contracts.pdf', ['subdomain' => request()->route('subdomain'), 'contract' => $contract->id]),
                'pdf_en' => route('admin.contracts.pdf', ['subdomain' => request()->route('subdomain'), 'contract' => $contract->id, 'lang' => 'en']),
                'pdf_ar' => route('admin.contracts.pdf', ['subdomain' => request()->route('subdomain'), 'contract' => $contract->id, 'lang' => 'ar']),
            ],
        ]);
    }

    public function pdf(Request $request, Contract $contract)
    {
        abort_unless($this->canAccessContract($contract, $request->user()), 403);

        $supportedLocales = array_values((array) config('app.available_locales', ['en', 'ar']));
        $fallbackLocale = (string) config('app.fallback_locale', config('app.locale', 'en'));
        $requestedLocale = strtolower((string) $request->query('lang', app()->getLocale()));
        $locale = in_array($requestedLocale, $supportedLocales, true) ? $requestedLocale : $fallbackLocale;

        app()->setLocale($locale);
        LaravelLocalization::setLocale($locale);

        $contract->loadMissing([
            'reservation.user:id,name,email',
            'reservation.car:id,make,model,year,license_plate',
            'branch:id,name',
            'tenant.siteSetting.files',
            'primaryDriver.documents',
            'additionalDrivers.documents',
            'damageReports.items',
        ]);

        $reportTypeLabels = collect(CarDamageCatalog::reportTypes())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $statusLabels = collect(CarDamageCatalog::statuses())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $damageTypeLabels = collect(CarDamageCatalog::damageTypes())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $severityLabels = collect(CarDamageCatalog::severityLevels())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $viewSideLabels = collect(CarDamageCatalog::viewSides())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $zoneLabels = CarDamageCatalog::zoneLabelMap();
        $direction = str_starts_with($locale, 'ar') ? 'rtl' : 'ltr';

        $currentDamageCases = $contract->reservation?->car?->id
            ? $this->serializeCarDamageCases((int) $contract->reservation->car->id, $request->user())
            : [];
        $branding = $this->pdfBranding($contract->tenant);

        return Pdf::view('admin.contracts.pdf', [
            'contract' => $contract,
            'currentDamageCases' => $currentDamageCases,
            'damageDiagram' => $this->buildPrintableDamageDiagram($currentDamageCases, $viewSideLabels),
            'reportTypeLabels' => $reportTypeLabels,
            'statusLabels' => $statusLabels,
            'damageTypeLabels' => $damageTypeLabels,
            'severityLabels' => $severityLabels,
            'viewSideLabels' => $viewSideLabels,
            'zoneLabels' => $zoneLabels,
            'generatedAt' => now(),
            'companyName' => $branding['name'],
            'companyLogo' => $branding['logo'],
            'currencySymbol' => config('app.currency_symbol', '$'),
            'locale' => $locale,
            'direction' => $direction,
        ])
            ->format(Format::A4)
            ->portrait()
            ->margins(12, 12, 12, 12)
            ->withBrowsershot(function (Browsershot $browsershot): void {
                $browsershot
                    ->waitUntilNetworkIdle(false)
                    ->timeout(120)
                    ->newHeadless();
            })
            ->download($contract->contract_number.'-'.$locale.'-report.pdf');
    }
    public function edit(Request $request, Contract $contract): Response
    {
        abort_unless($this->canAccessContract($contract, $request->user()), 403);
        $contract->loadMissing([
            'files',
            'primaryDriver.documents',
            'additionalDrivers.documents',
        ]);
        $reservationOptions = $this->reservationOptions($request);
        $carDamageMap = $this->serializeCarDamageCaseMap(
            collect($reservationOptions)
                ->pluck('car_id')
                ->push($contract->reservation?->car?->id)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
            $request->user()
        );

        return Inertia::render('Admin/Contracts/Edit', [
            'mode' => 'edit',
            'contract' => [
                'id' => $contract->id,
                'reservation_id' => $contract->reservation_id,
                'contract_number' => $contract->contract_number,
                'status' => $contract->status,
                'contract_date' => optional($contract->contract_date)->toDateString(),
                'renter_name' => $contract->renter_name,
                'renter_id_number' => $contract->renter_id_number,
                'renter_phone' => $contract->renter_phone,
                'car_details' => $contract->car_details,
                'plate_number' => $contract->plate_number,
                'start_date' => optional($contract->start_date)->toDateString(),
                'end_date' => optional($contract->end_date)->toDateString(),
                'total_amount' => $contract->total_amount,
                'currency' => $contract->currency,
                'notes' => $contract->notes,
                'ai_extracted_data' => $contract->ai_extracted_data,
                'ai_extraction_status' => $contract->ai_extraction_status,
                'primary_driver' => $contract->primaryDriver ? $this->serializeDriver($contract->primaryDriver) : $this->emptyDriverPayload('primary'),
                'additional_drivers' => $contract->additionalDrivers->map(fn (ContractDriver $driver) => $this->serializeDriver($driver))->values()->all(),
                'car_data' => $this->serializeCarData($contract),
                'contract_archive' => $this->serializeContractArchive($contract),
            ],
            'carData' => $this->serializeCarData($contract),
            'currentCarDamages' => $contract->reservation?->car?->id
                ? $this->serializeCarDamageCases((int) $contract->reservation->car->id, $request->user())
                : [],
            'carDamagesByCar' => $carDamageMap,
            'primaryDriver' => $contract->primaryDriver ? $this->serializeDriver($contract->primaryDriver) : $this->emptyDriverPayload('primary'),
            'additionalDrivers' => $contract->additionalDrivers->map(fn (ContractDriver $driver) => $this->serializeDriver($driver))->values()->all(),
            'contractArchive' => $this->serializeContractArchive($contract),
            'reservationOptions' => $reservationOptions,
            'reservationFormOptions' => [
                'clients' => $this->reservationClientOptions($request),
                'cars' => $this->reservationCarOptions($request),
            ],
            'startContractFiles' => $this->collectionFiles($contract, 'start_contract'),
            'endContractFiles' => $this->collectionFiles($contract, 'end_contract'),
            'ai' => [
                'contracts_extraction_enabled' => AiAutomationSettings::isContractsExtractionEnabled(),
            ],
            'actions' => [
                'index' => route('admin.contracts.index', ['subdomain' => $request->route('subdomain')]),
                'update' => route('admin.contracts.update', ['subdomain' => $request->route('subdomain'), 'contract' => $contract->id]),
                'show' => route('admin.contracts.show', ['subdomain' => $request->route('subdomain'), 'contract' => $contract->id]),
                'extract' => route('admin.contracts.extract', ['subdomain' => $request->route('subdomain')]),
                'extractDriver' => route('admin.contracts.drivers.extract', ['subdomain' => $request->route('subdomain')]),
                'reservationStore' => route('admin.reservations.store', ['subdomain' => $request->route('subdomain')]),
            ],
        ]);
    }

    public function extract(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'temp_folders' => ['required', 'array', 'min:1'],
            'temp_folders.*' => ['string'],
        ]);

        if (!AiAutomationSettings::isContractsExtractionEnabled()) {
            return response()->json([
                'message' => 'AI extraction is disabled by Super Admin settings.',
            ], 422);
        }

        if (!AiProviderSettings::isConfiguredForCurrentProvider()) {
            return response()->json([
                'message' => 'AI provider is not fully configured in Super Admin settings.',
            ], 422);
        }

        try {
            $result = $this->contractAiExtractor->extractFromTempFolders($validated['temp_folders']);

            return response()->json([
                'message' => 'AI extraction completed.',
                'fields' => $result['fields'] ?? [],
                'raw_output' => $result['raw_output'] ?? null,
                'text_preview' => $result['text_preview'] ?? '',
            ]);
        } catch (\Throwable $e) {
            $message = (string) $e->getMessage();
            $status = str_contains(strtolower($message), 'rate limit') ? 429 : 422;

            return response()->json([
                'message' => $message,
            ], $status);
        }
    }

    public function extractDriverDocument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver_role' => ['required', Rule::in(['primary', 'additional'])],
            'driver_index' => ['nullable', 'integer', 'min:0'],
            'document_type' => ['required', Rule::in(['driver_license', 'id_card', 'residency_card'])],
            'temp_folders' => ['required', 'array', 'min:1'],
            'temp_folders.*' => ['string'],
        ]);

        try {
            $result = $this->contractDriverDocumentExtractor->extractFromTempFolders(
                $validated['temp_folders'],
                (string) $validated['document_type']
            );

            return response()->json([
                'message' => 'Driver document extraction completed.',
                'driver_role' => $validated['driver_role'],
                'driver_index' => $validated['driver_index'] ?? null,
                'document_type' => $validated['document_type'],
                'fields' => $result['fields'],
                'raw_output' => $result['raw_output'],
                'raw_text' => $result['raw_text'],
                'confidence' => $result['confidence'],
                'provider' => $result['provider'],
                'engine' => $result['engine'],
                'status' => 'extracted',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => (string) $e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        abort_unless($this->canAccessContract($contract, $request->user()), 403);
        $tenantId = (int) (TenantContext::id() ?? 0);
        if ($tenantId <= 0) {
            abort(404);
        }

        $validated = $this->validatePayload($request, $tenantId, $contract->id);
        $reservation = $this->validatedReservation($validated['reservation_id'] ?? null, $request, $contract->id);

        DB::transaction(function () use ($request, $validated, $reservation, $contract) {
            $contract->branch_id = $reservation?->car?->branch_id ? (int) $reservation->car->branch_id : $contract->branch_id;
            if (!$contract->branch_id) {
                $contract->branch_id = $this->resolveContractBranchId($validated, $request);
            }
            $contract->reservation_id = $reservation?->id;
            $this->fillContract($contract, $validated, $reservation);
            $this->syncAiStatus($contract, $request, false);
            $contract->save();

            $this->syncFiles($contract, $request, 'start_contract');
            $this->syncFiles($contract, $request, 'end_contract');
            $this->syncContractDrivers($contract, $validated, $reservation);
        });

        return redirect()
            ->route('admin.contracts.show', [
                'subdomain' => $request->route('subdomain'),
                'contract' => $contract->id,
            ])
            ->with('success', 'Contract updated successfully.');
    }

    private function validatePayload(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        $uniqueRule = Rule::unique('contracts', 'contract_number')
            ->where(fn ($query) => $query->where('tenant_id', $tenantId));

        if ($ignoreId) {
            $uniqueRule->ignore($ignoreId);
        }

        return $request->validate([
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'contract_number' => ['nullable', 'string', 'max:100', $uniqueRule],
            'status' => ['required', Rule::in(['draft', 'active', 'completed', 'cancelled'])],
            'contract_date' => ['nullable', 'date'],
            'renter_name' => ['nullable', 'string', 'max:255'],
            'renter_id_number' => ['nullable', 'string', 'max:255'],
            'renter_phone' => ['nullable', 'string', 'max:100'],
            'car_details' => ['nullable', 'string', 'max:255'],
            'plate_number' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'ai_extracted_data' => ['nullable', 'array'],
            'car_data' => ['nullable', 'array'],
            'car_data.car_id' => ['nullable', 'integer', 'exists:cars,id'],
            'car_data.car_details' => ['nullable', 'string', 'max:255'],
            'car_data.plate_number' => ['nullable', 'string', 'max:255'],
            'car_data.branch_id' => ['nullable', 'integer', 'exists:branches,id'],

            'primary_driver' => ['nullable', 'array'],
            'primary_driver.id' => ['nullable', 'integer'],
            'primary_driver.client_id' => ['nullable', 'integer', 'exists:users,id'],
            'primary_driver.role' => ['nullable', Rule::in(['primary'])],
            'primary_driver.full_name' => ['nullable', 'string', 'max:255'],
            'primary_driver.phone' => ['nullable', 'string', 'max:100'],
            'primary_driver.nationality' => ['nullable', 'string', 'max:100'],
            'primary_driver.date_of_birth' => ['nullable', 'date'],
            'primary_driver.identity_number' => ['nullable', 'string', 'max:255'],
            'primary_driver.residency_number' => ['nullable', 'string', 'max:255'],
            'primary_driver.license_number' => ['nullable', 'string', 'max:255'],
            'primary_driver.identity_expiry_date' => ['nullable', 'date'],
            'primary_driver.license_expiry_date' => ['nullable', 'date'],
            'primary_driver.extraction_status' => ['nullable', 'string', 'max:50'],
            'primary_driver.extracted_data' => ['nullable', 'array'],
            'primary_driver.raw_output' => ['nullable', 'array'],
            'primary_driver.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'primary_driver.notes' => ['nullable', 'string'],
            'primary_driver.document_type' => ['nullable', 'string', 'max:100'],
            'primary_driver.temp_folders' => ['array'],
            'primary_driver.temp_folders.*' => ['string'],
            'primary_driver.removed_file_ids' => ['array'],
            'primary_driver.removed_file_ids.*' => ['integer'],
            'primary_driver.documents' => ['array'],
            'primary_driver.documents.*.id' => ['nullable', 'integer'],
            'primary_driver.documents.*.document_type' => ['nullable', 'string', 'max:100'],
            'primary_driver.documents.*.side' => ['nullable', Rule::in(['front', 'back', 'single'])],
            'primary_driver.documents.*.temp_folders' => ['array'],
            'primary_driver.documents.*.temp_folders.*' => ['string'],
            'primary_driver.documents.*.removed_file_ids' => ['array'],
            'primary_driver.documents.*.removed_file_ids.*' => ['integer'],

            'additional_drivers' => ['array'],
            'additional_drivers.*.id' => ['nullable', 'integer'],
            'additional_drivers.*.client_id' => ['nullable', 'integer', 'exists:users,id'],
            'additional_drivers.*.role' => ['nullable', Rule::in(['additional'])],
            'additional_drivers.*.full_name' => ['nullable', 'string', 'max:255'],
            'additional_drivers.*.phone' => ['nullable', 'string', 'max:100'],
            'additional_drivers.*.nationality' => ['nullable', 'string', 'max:100'],
            'additional_drivers.*.date_of_birth' => ['nullable', 'date'],
            'additional_drivers.*.identity_number' => ['nullable', 'string', 'max:255'],
            'additional_drivers.*.residency_number' => ['nullable', 'string', 'max:255'],
            'additional_drivers.*.license_number' => ['nullable', 'string', 'max:255'],
            'additional_drivers.*.identity_expiry_date' => ['nullable', 'date'],
            'additional_drivers.*.license_expiry_date' => ['nullable', 'date'],
            'additional_drivers.*.extraction_status' => ['nullable', 'string', 'max:50'],
            'additional_drivers.*.extracted_data' => ['nullable', 'array'],
            'additional_drivers.*.raw_output' => ['nullable', 'array'],
            'additional_drivers.*.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'additional_drivers.*.notes' => ['nullable', 'string'],
            'additional_drivers.*.document_type' => ['nullable', 'string', 'max:100'],
            'additional_drivers.*.temp_folders' => ['array'],
            'additional_drivers.*.temp_folders.*' => ['string'],
            'additional_drivers.*.removed_file_ids' => ['array'],
            'additional_drivers.*.removed_file_ids.*' => ['integer'],
            'additional_drivers.*.documents' => ['array'],
            'additional_drivers.*.documents.*.id' => ['nullable', 'integer'],
            'additional_drivers.*.documents.*.document_type' => ['nullable', 'string', 'max:100'],
            'additional_drivers.*.documents.*.side' => ['nullable', Rule::in(['front', 'back', 'single'])],
            'additional_drivers.*.documents.*.temp_folders' => ['array'],
            'additional_drivers.*.documents.*.temp_folders.*' => ['string'],
            'additional_drivers.*.documents.*.removed_file_ids' => ['array'],
            'additional_drivers.*.documents.*.removed_file_ids.*' => ['integer'],

            'contract_archive' => ['nullable', 'array'],
            'contract_archive.temp_folders' => ['array'],
            'contract_archive.temp_folders.*' => ['string'],
            'contract_archive.removed_file_ids' => ['array'],
            'contract_archive.removed_file_ids.*' => ['integer'],

            'start_contract_temp_folders' => ['array'],
            'start_contract_temp_folders.*' => ['string'],
            'start_contract_removed_files' => ['array'],
            'start_contract_removed_files.*' => ['integer'],
            'end_contract_temp_folders' => ['array'],
            'end_contract_temp_folders.*' => ['string'],
            'end_contract_removed_files' => ['array'],
            'end_contract_removed_files.*' => ['integer'],
        ]);
    }

    private function fillContract(Contract $contract, array $validated, ?Reservation $reservation): void
    {
        $carData = is_array($validated['car_data'] ?? null) ? $validated['car_data'] : [];
        $primaryDriver = $this->resolvedPrimaryDriverInput($validated, $reservation);

        $contract->contract_number = $this->nullableString($validated['contract_number'] ?? null)
            ?? $contract->contract_number
            ?? $this->generateContractNumber();
        $contract->status = (string) $validated['status'];
        $contract->contract_date = $validated['contract_date'] ?? null;
        $contract->renter_name = $this->nullableString($primaryDriver['full_name'] ?? null)
            ?? $this->nullableString($validated['renter_name'] ?? null)
            ?? $reservation?->user?->name;
        $contract->renter_id_number = $this->nullableString($primaryDriver['identity_number'] ?? null)
            ?? $this->nullableString($primaryDriver['residency_number'] ?? null)
            ?? $this->nullableString($validated['renter_id_number'] ?? null);
        $contract->renter_phone = $this->nullableString($primaryDriver['phone'] ?? null)
            ?? $this->nullableString($validated['renter_phone'] ?? null);
        $contract->car_details = $this->nullableString($carData['car_details'] ?? null)
            ?? $this->nullableString($validated['car_details'] ?? null)
            ?? ($reservation?->car ? "{$reservation->car->year} {$reservation->car->make} {$reservation->car->model}" : null);
        $contract->plate_number = $this->nullableString($carData['plate_number'] ?? null)
            ?? $this->nullableString($validated['plate_number'] ?? null)
            ?? $reservation?->car?->license_plate;
        $contract->start_date = $validated['start_date'] ?? $reservation?->start_date?->toDateString();
        $contract->end_date = $validated['end_date'] ?? $reservation?->end_date?->toDateString();
        $contract->total_amount = $validated['total_amount'] ?? $reservation?->total_amount;
        $contract->currency = strtoupper((string) ($validated['currency'] ?? 'USD'));
        $contract->notes = $this->nullableString($validated['notes'] ?? null);
        if (array_key_exists('ai_extracted_data', $validated)) {
            $contract->ai_extracted_data = is_array($validated['ai_extracted_data']) && !empty($validated['ai_extracted_data'])
                ? $validated['ai_extracted_data']
                : null;
        }
    }

    private function syncAiStatus(Contract $contract, Request $request, bool $isCreate): void
    {
        $aiEnabled = AiAutomationSettings::isContractsExtractionEnabled()
            && AiProviderSettings::isConfiguredForCurrentProvider();
        $hasNewUploads = !empty($request->input('start_contract_temp_folders', []))
            || !empty($request->input('end_contract_temp_folders', []));

        if (!$aiEnabled) {
            $contract->ai_extraction_status = 'disabled';
            $contract->ai_extracted_data = null;
            return;
        }

        if ($hasNewUploads) {
            $contract->ai_extraction_status = 'pending';
            return;
        }

        if ($isCreate) {
            $contract->ai_extraction_status = 'not_requested';
            return;
        }

        if (empty($contract->ai_extraction_status)) {
            $contract->ai_extraction_status = 'not_requested';
        }
    }

    private function validatedReservation(?int $reservationId, Request $request, ?int $currentContractId): ?Reservation
    {
        if (!$reservationId) {
            return null;
        }

        $reservation = Reservation::query()
            ->with(['user:id,name,email', 'car:id,branch_id,make,model,year,license_plate'])
            ->find($reservationId);

        if (!$reservation) {
            return null;
        }

        $this->ensureReservationAccessible($reservation, $request->user(), $currentContractId);

        return $reservation;
    }

    private function reservationOptions(Request $request): array
    {
        $user = $request->user();
        $query = Reservation::query()
            ->with(['user:id,name,email', 'car:id,branch_id,make,model,year,license_plate', 'contract:id,reservation_id'])
            ->latest('id')
            ->limit(100);

        if ($this->branchAccess->canAccessAllBranches($user)) {
            // no-op
        } else {
            $userBranchId = (int) ($user?->branch_id ?? 0);
            if ($userBranchId <= 0) {
                return [];
            }
            $query->whereHas('car', fn ($carQuery) => $carQuery->where('branch_id', $userBranchId));
        }

        return $query->get()
            ->map(function (Reservation $reservation) {
                $hasContract = (bool) $reservation->contract;
                return [
                    'id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'label' => "{$reservation->reservation_number} - {$reservation->user?->name}",
                    'car' => $reservation->car ? "{$reservation->car->year} {$reservation->car->make} {$reservation->car->model}" : null,
                    'car_id' => $reservation->car?->id,
                    'car_details' => $reservation->car ? "{$reservation->car->year} {$reservation->car->make} {$reservation->car->model}" : null,
                    'plate_number' => $reservation->car?->license_plate,
                    'branch_id' => $reservation->car?->branch_id,
                    'user_id' => $reservation->user?->id,
                    'user_name' => $reservation->user?->name,
                    'start_date' => optional($reservation->start_date)->toDateString(),
                    'end_date' => optional($reservation->end_date)->toDateString(),
                    'total_amount' => $reservation->total_amount,
                    'has_contract' => $hasContract,
                ];
            })
            ->values()
            ->all();
    }

    private function reservationClientOptions(Request $request): array
    {
        return User::query()
            ->where('tenant_id', $request->user()?->tenant_id)
            ->where('role', 'client')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
            ])
            ->values()
            ->all();
    }

    private function reservationCarOptions(Request $request): array
    {
        $query = Car::query()
            ->where('tenant_id', $request->user()?->tenant_id)
            ->with('branch:id,name')
            ->orderBy('make')
            ->orderBy('model');

        if (!$this->branchAccess->canAccessAllBranches($request->user())) {
            $userBranchId = (int) ($request->user()?->branch_id ?? 0);
            if ($userBranchId <= 0) {
                return [];
            }

            $query->where('branch_id', $userBranchId);
        }

        return $query->get(['id', 'branch_id', 'make', 'model', 'year', 'license_plate', 'price_per_day'])
            ->map(fn (Car $car) => [
                'id' => $car->id,
                'label' => sprintf('%s %s %s', $car->year, $car->make, $car->model),
                'license_plate' => $car->license_plate,
                'branch_name' => $car->branch?->name,
                'price_per_day' => (float) $car->price_per_day,
            ])
            ->values()
            ->all();
    }

    private function prefillFromReservation(Reservation $reservation): array
    {
        return [
            'reservation_id' => $reservation->id,
            'contract_number' => $this->generateContractNumber(),
            'status' => 'draft',
            'contract_date' => now()->toDateString(),
            'renter_name' => $reservation->user?->name,
            'renter_id_number' => null,
            'renter_phone' => null,
            'car_details' => $reservation->car ? "{$reservation->car->year} {$reservation->car->make} {$reservation->car->model}" : null,
            'plate_number' => $reservation->car?->license_plate,
            'start_date' => optional($reservation->start_date)->toDateString(),
            'end_date' => optional($reservation->end_date)->toDateString(),
            'total_amount' => $reservation->total_amount,
            'currency' => strtoupper((string) config('app.currency_code', 'USD')),
            'notes' => null,
            'ai_extracted_data' => null,
            'ai_extraction_status' => AiAutomationSettings::isContractsExtractionEnabled() ? 'not_requested' : 'disabled',
            'primary_driver' => $this->prefillPrimaryDriverFromReservation($reservation),
            'additional_drivers' => [],
            'car_data' => $this->prefillCarDataFromReservation($reservation),
            'contract_archive' => $this->emptyContractArchivePayload(),
        ];
    }

    private function generateContractNumber(): string
    {
        $tenantId = (int) (TenantContext::id() ?? 0);
        $datePrefix = now()->format('Ymd');

        $latest = Contract::query()
            ->when($tenantId > 0, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('contract_number', 'like', "CTR-{$datePrefix}-%")
            ->latest('id')
            ->value('contract_number');

        $nextSequence = 1;
        if (is_string($latest) && preg_match('/CTR-\d{8}-(\d{4})$/', $latest, $matches)) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        return sprintf('CTR-%s-%04d', $datePrefix, $nextSequence);
    }

    private function ensureReservationAccessible(Reservation $reservation, $user, ?int $currentContractId): void
    {
        $reservation->loadMissing('car:id,branch_id', 'contract:id,reservation_id');

        if ($reservation->contract && (int) $reservation->contract->id !== (int) $currentContractId) {
            throw ValidationException::withMessages([
                'reservation_id' => 'This reservation already has a contract.',
            ]);
        }

        $canAccess = $this->branchAccess->canAccessBranchId(
            $user,
            $reservation->car?->branch_id ? (int) $reservation->car->branch_id : null
        );

        if (!$canAccess) {
            abort(403);
        }
    }

    private function applyContractBranchScope($query, $user, ?int $branchId): void
    {
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        if ($canAccessAllBranches) {
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where('branch_id', $userBranchId);
    }

    private function canAccessContract(Contract $contract, $user): bool
    {
        return $this->branchAccess->canAccessBranchId(
            $user,
            $contract->branch_id ? (int) $contract->branch_id : null
        );
    }

    private function syncFiles(Contract $contract, Request $request, string $collection): void
    {
        $tempFolders = $request->input("{$collection}_temp_folders", []);
        $removedIds = $request->input("{$collection}_removed_files", []);

        $tempFolders = is_array($tempFolders) ? $tempFolders : [];
        $removedIds = is_array($removedIds) ? $removedIds : [];

        if (!empty($tempFolders)) {
            $existingIds = $contract->files()->where('collection', $collection)->pluck('id')->all();
            $removedIds = array_values(array_unique(array_merge($removedIds, $existingIds)));
        }

        $this->filePondService->handleFileUpdates(
            $contract,
            $tempFolders,
            $removedIds,
            $collection
        );
    }

    private function collectionFiles(Contract $contract, string $collection): array
    {
        $files = $contract->relationLoaded('files')
            ? $contract->files->where('collection', $collection)->values()
            : $contract->files()->where('collection', $collection)->get();

        return $files->map(fn ($file) => [
            'id' => $file->id,
            'url' => $this->storageUrl($file->path),
        ])->values()->all();
    }

    private function firstFileMeta(Contract $contract, string $collection): ?array
    {
        $file = $contract->relationLoaded('files')
            ? $contract->files->firstWhere('collection', $collection)
            : $contract->files()->where('collection', $collection)->first();

        if (!$file || !$file->path) {
            return null;
        }

        return [
            'id' => $file->id,
            'name' => basename((string) $file->path),
            'url' => $this->storageUrl($file->path),
        ];
    }

    private function storageUrl(?string $path): ?string
    {
        $path = trim((string) ($path ?? ''));
        if ($path === '') {
            return null;
        }

        $normalized = ltrim($path, '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        return Storage::url($normalized);
    }

    private function resolveContractBranchId(array $validated, Request $request): ?int
    {
        $carData = is_array($validated['car_data'] ?? null) ? $validated['car_data'] : [];
        $branchId = isset($carData['branch_id']) ? (int) $carData['branch_id'] : null;

        if ($branchId) {
            return $this->branchAccess->resolveWritableBranchId($request->user(), $branchId);
        }

        return $this->branchAccess->resolveWritableBranchId($request->user(), null);
    }

    private function resolvedPrimaryDriverInput(array $validated, ?Reservation $reservation): array
    {
        $payload = is_array($validated['primary_driver'] ?? null) ? $validated['primary_driver'] : [];

        return array_merge($this->emptyDriverPayload('primary'), [
            'id' => $payload['id'] ?? null,
            'client_id' => $payload['client_id'] ?? null,
            'full_name' => $payload['full_name'] ?? $validated['renter_name'] ?? $reservation?->user?->name,
            'phone' => $payload['phone'] ?? $validated['renter_phone'] ?? null,
            'nationality' => $payload['nationality'] ?? null,
            'date_of_birth' => $payload['date_of_birth'] ?? null,
            'identity_number' => $payload['identity_number'] ?? $validated['renter_id_number'] ?? null,
            'residency_number' => $payload['residency_number'] ?? null,
            'license_number' => $payload['license_number'] ?? null,
            'identity_expiry_date' => $payload['identity_expiry_date'] ?? null,
            'license_expiry_date' => $payload['license_expiry_date'] ?? null,
            'extraction_status' => $payload['extraction_status'] ?? 'not_requested',
            'extracted_data' => is_array($payload['extracted_data'] ?? null) ? $payload['extracted_data'] : null,
            'raw_output' => is_array($payload['raw_output'] ?? null) ? $payload['raw_output'] : null,
            'confidence' => $payload['confidence'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'document_type' => $payload['document_type'] ?? null,
            'temp_folders' => is_array($payload['temp_folders'] ?? null) ? $payload['temp_folders'] : [],
            'removed_file_ids' => is_array($payload['removed_file_ids'] ?? null) ? $payload['removed_file_ids'] : [],
            'documents' => is_array($payload['documents'] ?? null) ? $payload['documents'] : [],
        ]);
    }

    private function syncContractDrivers(Contract $contract, array $validated, ?Reservation $reservation): void
    {
        $primaryPayload = $this->resolvedPrimaryDriverInput($validated, $reservation);
        $primaryDriver = $contract->primaryDriver()->first() ?? new ContractDriver([
            'tenant_id' => $contract->tenant_id,
            'contract_id' => $contract->id,
            'role' => 'primary',
        ]);

        $this->fillDriverModel($primaryDriver, $primaryPayload, 'primary', 0);
        $primaryDriver->save();
        $this->syncDriverDocuments($primaryDriver, $primaryPayload);

        $keepDriverIds = [$primaryDriver->id];
        $additionalDrivers = is_array($validated['additional_drivers'] ?? null) ? $validated['additional_drivers'] : [];
        $existingAdditional = $contract->additionalDrivers()->with('documents')->get()->keyBy('id');

        foreach ($additionalDrivers as $index => $payload) {
            if (!is_array($payload) || !$this->driverPayloadHasData($payload)) {
                continue;
            }

            $driverId = isset($payload['id']) ? (int) $payload['id'] : 0;
            $driver = $driverId > 0
                ? ($existingAdditional->get($driverId) ?? new ContractDriver([
                    'tenant_id' => $contract->tenant_id,
                    'contract_id' => $contract->id,
                    'role' => 'additional',
                ]))
                : new ContractDriver([
                    'tenant_id' => $contract->tenant_id,
                    'contract_id' => $contract->id,
                    'role' => 'additional',
                ]);

            $this->fillDriverModel($driver, $payload, 'additional', $index);
            $driver->save();
            $this->syncDriverDocuments($driver, $payload);
            $keepDriverIds[] = $driver->id;
        }

        $contract->drivers()
            ->whereNotIn('id', $keepDriverIds)
            ->with('documents')
            ->get()
            ->each(function (ContractDriver $driver) {
                $driver->documents->each(fn (ContractDriverDocument $document) => $document->delete());
                $driver->delete();
            });
    }

    private function fillDriverModel(ContractDriver $driver, array $payload, string $role, int $sortOrder): void
    {
        $clientId = isset($payload['client_id']) && is_numeric($payload['client_id']) ? (int) $payload['client_id'] : null;
        if ($clientId) {
            $client = User::query()->find($clientId);
            if (!$client || (int) $client->tenant_id !== (int) $driver->tenant_id) {
                throw ValidationException::withMessages([
                    $role === 'primary' ? 'primary_driver.client_id' : 'additional_drivers' => 'Selected client is invalid for this tenant.',
                ]);
            }
        }

        $driver->client_id = $clientId ?: null;
        $driver->role = $role;
        $driver->sort_order = $sortOrder;
        $driver->full_name = $this->nullableString($payload['full_name'] ?? null);
        $driver->phone = $this->nullableString($payload['phone'] ?? null);
        $driver->nationality = $this->nullableString($payload['nationality'] ?? null);
        $driver->date_of_birth = $payload['date_of_birth'] ?? null;
        $driver->identity_number = $this->nullableString($payload['identity_number'] ?? null);
        $driver->residency_number = $this->nullableString($payload['residency_number'] ?? null);
        $driver->license_number = $this->nullableString($payload['license_number'] ?? null);
        $driver->identity_expiry_date = $payload['identity_expiry_date'] ?? null;
        $driver->license_expiry_date = $payload['license_expiry_date'] ?? null;
        $driver->extraction_status = $this->nullableString($payload['extraction_status'] ?? null) ?? 'not_requested';
        $driver->extracted_data = is_array($payload['extracted_data'] ?? null) && !empty($payload['extracted_data']) ? $payload['extracted_data'] : null;
        $driver->raw_output = is_array($payload['raw_output'] ?? null) && !empty($payload['raw_output']) ? $payload['raw_output'] : null;
        $driver->confidence = isset($payload['confidence']) && is_numeric($payload['confidence']) ? (float) $payload['confidence'] : null;
        $driver->notes = $this->nullableString($payload['notes'] ?? null);
    }

    private function syncDriverDocuments(ContractDriver $driver, array $payload): void
    {
        $documentsInput = $this->normalizedDriverDocumentsInput($payload);
        if ($documentsInput === []) {
            return;
        }

        foreach ($documentsInput as $documentInput) {
            $removedIds = array_map('intval', is_array($documentInput['removed_file_ids'] ?? null) ? $documentInput['removed_file_ids'] : []);
            if ($removedIds !== []) {
                $driver->documents()->whereIn('id', $removedIds)->get()->each->delete();
            }

            $tempFolders = is_array($documentInput['temp_folders'] ?? null) ? array_values(array_filter($documentInput['temp_folders'])) : [];
            foreach ($tempFolders as $index => $folder) {
                $this->moveTempFileToDriverDocument(
                    $driver,
                    (string) $folder,
                    (string) ($documentInput['document_type'] ?? 'document'),
                    $this->resolveDocumentSide($documentInput, $index, count($tempFolders))
                );
            }
        }
    }

    private function normalizedDriverDocumentsInput(array $payload): array
    {
        $documents = is_array($payload['documents'] ?? null) ? array_values(array_filter($payload['documents'], 'is_array')) : [];
        if ($documents !== []) {
            return $documents;
        }

        $documentType = $this->nullableString($payload['document_type'] ?? null);
        $tempFolders = is_array($payload['temp_folders'] ?? null) ? $payload['temp_folders'] : [];
        $removedFileIds = is_array($payload['removed_file_ids'] ?? null) ? $payload['removed_file_ids'] : [];

        if ($documentType === null && $tempFolders === [] && $removedFileIds === []) {
            return [];
        }

        return [[
            'document_type' => $documentType ?? 'document',
            'temp_folders' => $tempFolders,
            'removed_file_ids' => $removedFileIds,
            'side' => null,
        ]];
    }

    private function resolveDocumentSide(array $documentInput, int $index, int $count): string
    {
        $explicit = $this->nullableString($documentInput['side'] ?? null);
        if ($explicit && in_array($explicit, ['front', 'back', 'single'], true)) {
            return $explicit;
        }

        if ($count === 1) {
            return 'single';
        }

        return $index === 0 ? 'front' : ($index === 1 ? 'back' : 'single');
    }

    private function moveTempFileToDriverDocument(
        ContractDriver $driver,
        string $folder,
        string $documentType,
        string $side
    ): ?ContractDriverDocument {
        $tempFile = TempFile::query()->where('folder', $folder)->first();
        if (!$tempFile) {
            return null;
        }

        $disk = Storage::disk(config('vilt-filepond.storage_disk'));
        $extension = pathinfo((string) $tempFile->filename, PATHINFO_EXTENSION);
        $filename = 'contract_driver_document_'.$driver->id.'_'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        $newPath = config('vilt-filepond.files_path').'/contractdriverdocument/'.$driver->id.'/'.$documentType.'/'.$filename;

        $disk->move($tempFile->path, $newPath);

        $document = ContractDriverDocument::create([
            'tenant_id' => $driver->tenant_id,
            'contract_driver_id' => $driver->id,
            'document_type' => $documentType,
            'side' => $side,
            'file_path' => 'storage/'.$newPath,
            'file_name' => $tempFile->original_name,
            'mime_type' => $tempFile->mime_type,
            'ocr_status' => 'pending',
        ]);

        $disk->deleteDirectory(config('vilt-filepond.temp_path').'/'.$tempFile->folder);
        $tempFile->delete();

        return $document;
    }

    private function driverPayloadHasData(array $payload): bool
    {
        $keys = [
            'client_id',
            'full_name',
            'phone',
            'nationality',
            'date_of_birth',
            'identity_number',
            'residency_number',
            'license_number',
            'identity_expiry_date',
            'license_expiry_date',
            'document_type',
        ];

        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_array($value) && !empty($value)) {
                return true;
            }
            if (!is_array($value) && $this->nullableString($value) !== null) {
                return true;
            }
        }

        return !empty($payload['temp_folders']) || !empty($payload['documents']);
    }

    private function emptyDriverPayload(string $role = 'primary'): array
    {
        return [
            'id' => null,
            'client_id' => null,
            'role' => $role,
            'full_name' => '',
            'phone' => '',
            'nationality' => '',
            'date_of_birth' => '',
            'identity_number' => '',
            'residency_number' => '',
            'license_number' => '',
            'identity_expiry_date' => '',
            'license_expiry_date' => '',
            'extraction_status' => 'not_requested',
            'extracted_data' => null,
            'raw_output' => null,
            'confidence' => null,
            'notes' => '',
            'document_type' => '',
            'temp_folders' => [],
            'removed_file_ids' => [],
            'documents' => [],
        ];
    }

    private function emptyCarData(): array
    {
        return [
            'car_id' => null,
            'car_details' => '',
            'plate_number' => '',
            'branch_id' => null,
        ];
    }

    private function emptyContractArchivePayload(): array
    {
        return [
            'temp_folders' => [],
            'removed_file_ids' => [],
            'files' => [],
        ];
    }

    private function prefillPrimaryDriverFromReservation(Reservation $reservation): array
    {
        return array_merge($this->emptyDriverPayload('primary'), [
            'client_id' => $reservation->user?->id,
            'full_name' => $reservation->user?->name ?? '',
        ]);
    }

    private function prefillCarDataFromReservation(Reservation $reservation): array
    {
        return [
            'car_id' => $reservation->car?->id,
            'car_details' => $reservation->car ? "{$reservation->car->year} {$reservation->car->make} {$reservation->car->model}" : '',
            'plate_number' => $reservation->car?->license_plate,
            'branch_id' => $reservation->car?->branch_id,
        ];
    }

    private function serializeDriver(ContractDriver $driver): array
    {
        $driver->loadMissing('documents');

        return [
            'id' => $driver->id,
            'client_id' => $driver->client_id,
            'role' => $driver->role,
            'full_name' => $driver->full_name,
            'phone' => $driver->phone,
            'nationality' => $driver->nationality,
            'date_of_birth' => optional($driver->date_of_birth)->toDateString(),
            'identity_number' => $driver->identity_number,
            'residency_number' => $driver->residency_number,
            'license_number' => $driver->license_number,
            'identity_expiry_date' => optional($driver->identity_expiry_date)->toDateString(),
            'license_expiry_date' => optional($driver->license_expiry_date)->toDateString(),
            'extraction_status' => $driver->extraction_status,
            'extracted_data' => $driver->extracted_data,
            'raw_output' => $driver->raw_output,
            'confidence' => $driver->confidence !== null ? (float) $driver->confidence : null,
            'notes' => $driver->notes,
            'document_type' => $driver->documents->first()?->document_type,
            'temp_folders' => [],
            'removed_file_ids' => [],
            'documents' => $driver->documents->map(fn (ContractDriverDocument $document) => $this->serializeDriverDocument($document))->values()->all(),
        ];
    }

    private function serializeDriverDocument(ContractDriverDocument $document): array
    {
        return [
            'id' => $document->id,
            'document_type' => $document->document_type,
            'side' => $document->side,
            'file_path' => $document->file_path,
            'file_name' => $document->file_name,
            'mime_type' => $document->mime_type,
            'ocr_status' => $document->ocr_status,
            'ocr_provider' => $document->ocr_provider,
            'raw_ocr_json' => $document->raw_ocr_json,
            'normalized_json' => $document->normalized_json,
            'confidence' => $document->confidence !== null ? (float) $document->confidence : null,
            'reviewed_at' => optional($document->reviewed_at)->toDateTimeString(),
            'url' => $this->storageUrl($document->file_path),
        ];
    }

    private function serializeCarData(Contract $contract): array
    {
        return [
            'car_id' => $contract->reservation?->car?->id,
            'car_details' => $contract->car_details,
            'plate_number' => $contract->plate_number,
            'branch_id' => $contract->branch_id,
        ];
    }

    private function serializeContractArchive(Contract $contract): array
    {
        return [
            'temp_folders' => [],
            'removed_file_ids' => [],
            'files' => [
                ...$this->collectionFiles($contract, 'start_contract'),
                ...$this->collectionFiles($contract, 'end_contract'),
            ],
        ];
    }


    private function pdfBranding($tenant): array
    {
        $tenant = $tenant?->loadMissing('siteSetting.files');
        $settings = $tenant ? TenantSiteSetting::forTenant($tenant) : [];
        $name = trim((string) ($settings['site_name'] ?? $tenant?->name ?? config('app.name')));

        return [
            'name' => $name !== '' ? $name : (string) config('app.name'),
            'logo' => $this->pdfImageSource($settings['logo_url'] ?? null),
        ];
    }

    private function pdfImageSource(?string $url): ?string
    {
        $url = trim((string) ($url ?? ''));
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'data:') || preg_match('/^https?:\/\//i', $url) === 1) {
            return $url;
        }

        $path = null;

        if (str_starts_with($url, '/storage/')) {
            $path = public_path(ltrim($url, '/'));
        } elseif (str_starts_with($url, 'storage/')) {
            $path = public_path($url);
        } elseif (str_starts_with($url, '/')) {
            $path = public_path(ltrim($url, '/'));
        }

        if (!$path || !is_file($path)) {
            return $url;
        }

        $contents = file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            return null;
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function serializeCarDamageCases(int $carId, $user): array
    {
        $query = CarDamageCase::query()
            ->where('car_id', $carId)
            ->where('status', 'open')
            ->orderBy('zone_code')
            ->orderBy('id');

        $this->branchAccess->applyToQuery($query, $user, null, 'branch_id');

        $zoneLabels = CarDamageCatalog::zoneLabelMap();
        $viewLabels = collect(CarDamageCatalog::viewSides())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $damageTypeLabels = collect(CarDamageCatalog::damageTypes())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $severityLabels = collect(CarDamageCatalog::severityLevels())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();

        return $query->get()->map(function (CarDamageCase $case) use ($zoneLabels, $viewLabels, $damageTypeLabels, $severityLabels) {
            return [
                'id' => $case->id,
                'zone_code' => $case->zone_code,
                'zone_label' => $zoneLabels[$case->zone_code] ?? $case->zone_code,
                'view_side' => $case->view_side,
                'view_side_label' => $viewLabels[$case->view_side] ?? $case->view_side,
                'damage_type' => $case->damage_type,
                'damage_type_label' => $damageTypeLabels[$case->damage_type] ?? $case->damage_type,
                'severity' => $case->severity,
                'severity_label' => $severityLabels[$case->severity] ?? $case->severity,
                'quantity' => (int) $case->quantity,
                'notes' => $case->notes,
                'first_detected_at' => optional($case->first_detected_at)?->format('Y-m-d H:i'),
            ];
        })->values()->all();
    }

    private function serializeCarDamageCaseMap(array $carIds, $user): array
    {
        $map = [];

        foreach ($carIds as $carId) {
            $normalizedCarId = (int) $carId;
            if ($normalizedCarId <= 0) {
                continue;
            }

            $map[$normalizedCarId] = $this->serializeCarDamageCases($normalizedCarId, $user);
        }

        return $map;
    }

    private function buildPrintableDamageDiagram(array $damageCases, array $viewSideLabels): array
    {
        $layout = $this->printableDamageDiagramLayout();
        $zoneViews = collect(CarDamageCatalog::zoneViews());
        $markers = [];

        foreach (array_values($damageCases) as $index => $damage) {
            if (!is_array($damage)) {
                continue;
            }

            $zone = $zoneViews->first(function (array $view) use ($damage): bool {
                return ($view['code'] ?? null) === ($damage['zone_code'] ?? null)
                    && ($view['view_side'] ?? null) === ($damage['view_side'] ?? null);
            });

            if ($zone === null) {
                $zone = $zoneViews->first(fn (array $view): bool => ($view['code'] ?? null) === ($damage['zone_code'] ?? null));
            }

            if ($zone === null) {
                continue;
            }

            $viewSide = (string) ($zone['view_side'] ?? $damage['view_side'] ?? '');
            $viewLayout = $layout['views'][$viewSide] ?? null;
            if ($viewLayout === null) {
                continue;
            }

            $centerX = (float) $zone['x'] + ((float) $zone['width'] / 2);
            $centerY = (float) $zone['y'] + ((float) $zone['height'] / 2);
            $point = $this->transformPrintableDamagePoint($viewLayout, $centerX, $centerY);

            $markers[] = [
                'number' => $index + 1,
                'view_side' => $viewSide,
                'x' => $point['x'],
                'y' => $point['y'],
                'zone_label' => $damage['zone_label'] ?? ($damage['zone_code'] ?? '-'),
                'view_side_label' => $damage['view_side_label'] ?? $viewSide,
                'damage_type_label' => $damage['damage_type_label'] ?? ($damage['damage_type'] ?? '-'),
                'severity_label' => $damage['severity_label'] ?? ($damage['severity'] ?? '-'),
                'quantity' => (int) ($damage['quantity'] ?? 0),
                'notes' => $damage['notes'] ?? null,
            ];
        }

        return [
            'canvas_width' => 785,
            'canvas_height' => 483,
            'views' => $layout['views'],
            'markers' => $markers,
            'data_uri' => 'data:image/svg+xml;base64,'.base64_encode(
                $this->renderPrintableDamageDiagramSvg($layout['views'], $markers, $viewSideLabels)
            ),
        ];
    }

    private function renderPrintableDamageDiagramSvg(array $views, array $markers, array $viewSideLabels): string
    {
        $escape = static fn (?string $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $parts = [
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 785 483" width="785" height="483">',
            $this->printableDamageDiagramAssetMarkup(),
        ];

        foreach ($markers as $marker) {
            $parts[] = '<circle cx="'.$marker['x'].'" cy="'.$marker['y'].'" r="12" fill="#111827" stroke="#FFFFFF" stroke-width="3" />';
            $parts[] = '<text x="'.$marker['x'].'" y="'.($marker['y'] + 4).'" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="12" font-weight="700" fill="#FFFFFF">'.$escape((string) $marker['number']).'</text>';
        }

        $parts[] = '</svg>';

        return implode('', $parts);
    }

    private function printableDamageDiagramAssetMarkup(): string
    {
        $path = public_path('images/contract-damage-layout.svg');
        if (!is_file($path)) {
            return '<rect x="1" y="1" width="758" height="468" rx="12" fill="#FFFFFF" stroke="#E5E7EB" stroke-width="2" />';
        }

        $contents = file_get_contents($path);
        if (!is_string($contents) || trim($contents) === '') {
            return '<rect x="1" y="1" width="758" height="468" rx="12" fill="#FFFFFF" stroke="#E5E7EB" stroke-width="2" />';
        }

        $contents = preg_replace('/<\?xml.*?\?>/s', '', $contents) ?? $contents;

        if (preg_match('/<svg[^>]*>(.*)<\/svg>/si', $contents, $matches) === 1) {
            return $matches[1];
        }

        return $contents;
    }

    private function printableDamageDiagramLayout(): array
    {
        return [
            'views' => [
                'front' => ['x' => 52, 'y' => 170, 'scale_x' => 0.62, 'scale_y' => 0.62, 'rotation' => 'cw', 'source_width' => 320, 'source_height' => 160, 'width' => 99, 'height' => 198],
                'top' => ['x' => 194, 'y' => 165, 'scale_x' => 1.18, 'scale_y' => 0.96, 'rotation' => 'none', 'source_width' => 320, 'source_height' => 160, 'width' => 378, 'height' => 154],
                'rear' => ['x' => 587, 'y' => 170, 'scale_x' => 0.62, 'scale_y' => 0.62, 'rotation' => 'ccw', 'source_width' => 320, 'source_height' => 160, 'width' => 99, 'height' => 198],
                'left' => ['x' => 191, 'y' => 328, 'scale_x' => 1.17, 'scale_y' => 0.78, 'rotation' => 'none', 'source_width' => 320, 'source_height' => 160, 'width' => 374, 'height' => 125],
                'right' => ['x' => 191, 'y' => 35, 'scale_x' => 1.17, 'scale_y' => 0.78, 'rotation' => 'flip', 'source_width' => 320, 'source_height' => 160, 'width' => 374, 'height' => 125],
            ],
        ];
    }

    private function transformPrintableDamagePoint(array $viewLayout, float $x, float $y): array
    {
        $sourceWidth = (float) ($viewLayout['source_width'] ?? 320);
        $sourceHeight = (float) ($viewLayout['source_height'] ?? 160);
        $scaleX = (float) ($viewLayout['scale_x'] ?? $viewLayout['scale'] ?? 1);
        $scaleY = (float) ($viewLayout['scale_y'] ?? $viewLayout['scale'] ?? 1);
        $rotation = (string) ($viewLayout['rotation'] ?? 'none');

        $mappedX = $x;
        $mappedY = $y;

        if ($rotation === 'cw') {
            $mappedX = $sourceHeight - $y;
            $mappedY = $x;
        } elseif ($rotation === 'ccw') {
            $mappedX = $y;
            $mappedY = $sourceWidth - $x;
        } elseif ($rotation === 'flip') {
            $mappedX = $sourceWidth - $x;
            $mappedY = $sourceHeight - $y;
        }

        return [
            'x' => round((float) $viewLayout['x'] + ($mappedX * $scaleX), 1),
            'y' => round((float) $viewLayout['y'] + ($mappedY * $scaleY), 1),
        ];
    }
}

