<?php

namespace App\Http\Controllers\Admin;

use App\Core\AiAutomationSettings;
use App\Core\AiProviderSettings;
use App\Core\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Reservation;
use App\Services\Contracts\ContractAiExtractor;
use App\Support\BranchAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class ContractsController extends Controller
{
    public function __construct(
        private BranchAccess $branchAccess,
        private FilePondService $filePondService,
        private ContractAiExtractor $contractAiExtractor
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

        return Inertia::render('Admin/Contracts/Edit', [
            'mode' => 'create',
            'contract' => $reservation ? $this->prefillFromReservation($reservation) : null,
            'reservationOptions' => $reservations,
            'startContractFiles' => [],
            'endContractFiles' => [],
            'ai' => [
                'contracts_extraction_enabled' => AiAutomationSettings::isContractsExtractionEnabled(),
            ],
            'actions' => [
                'index' => route('admin.contracts.index', ['subdomain' => $request->route('subdomain')]),
                'store' => route('admin.contracts.store', ['subdomain' => $request->route('subdomain')]),
                'extract' => route('admin.contracts.extract', ['subdomain' => $request->route('subdomain')]),
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

        $contract = new Contract();
        $contract->tenant_id = $tenantId;
        $contract->branch_id = $reservation?->car?->branch_id ? (int) $reservation->car->branch_id : null;
        if (!$contract->branch_id) {
            $contract->branch_id = $this->branchAccess->resolveWritableBranchId(
                $request->user(),
                null
            );
        }
        $contract->reservation_id = $reservation?->id;
        $this->fillContract($contract, $validated, $reservation);
        $this->syncAiStatus($contract, $request, true);
        $contract->save();

        $this->syncFiles($contract, $request, 'start_contract');
        $this->syncFiles($contract, $request, 'end_contract');

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
        ]);

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
            ],
            'startRentalDocument' => $this->firstFileMeta($contract, 'start_contract'),
            'endRentalDocument' => $this->firstFileMeta($contract, 'end_contract'),
            'actions' => [
                'index' => route('admin.contracts.index', ['subdomain' => request()->route('subdomain')]),
                'edit' => route('admin.contracts.edit', ['subdomain' => request()->route('subdomain'), 'contract' => $contract->id]),
            ],
        ]);
    }

    public function edit(Request $request, Contract $contract): Response
    {
        abort_unless($this->canAccessContract($contract, $request->user()), 403);
        $contract->loadMissing('files');

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
            ],
            'reservationOptions' => $this->reservationOptions($request),
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

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        abort_unless($this->canAccessContract($contract, $request->user()), 403);
        $tenantId = (int) (TenantContext::id() ?? 0);
        if ($tenantId <= 0) {
            abort(404);
        }

        $validated = $this->validatePayload($request, $tenantId, $contract->id);
        $reservation = $this->validatedReservation($validated['reservation_id'] ?? null, $request, $contract->id);

        $contract->branch_id = $reservation?->car?->branch_id ? (int) $reservation->car->branch_id : $contract->branch_id;
        if (!$contract->branch_id) {
            $contract->branch_id = $this->branchAccess->resolveWritableBranchId(
                $request->user(),
                null
            );
        }
        $contract->reservation_id = $reservation?->id;
        $this->fillContract($contract, $validated, $reservation);
        $this->syncAiStatus($contract, $request, false);
        $contract->save();

        $this->syncFiles($contract, $request, 'start_contract');
        $this->syncFiles($contract, $request, 'end_contract');

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
            'contract_number' => ['required', 'string', 'max:100', $uniqueRule],
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
        $contract->contract_number = (string) $validated['contract_number'];
        $contract->status = (string) $validated['status'];
        $contract->contract_date = $validated['contract_date'] ?? null;
        $contract->renter_name = $this->nullableString($validated['renter_name'] ?? null)
            ?? $reservation?->user?->name;
        $contract->renter_id_number = $this->nullableString($validated['renter_id_number'] ?? null);
        $contract->renter_phone = $this->nullableString($validated['renter_phone'] ?? null);
        $contract->car_details = $this->nullableString($validated['car_details'] ?? null)
            ?? ($reservation?->car ? "{$reservation->car->year} {$reservation->car->make} {$reservation->car->model}" : null);
        $contract->plate_number = $this->nullableString($validated['plate_number'] ?? null)
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
                    'branch_id' => $reservation->car?->branch_id,
                    'has_contract' => $hasContract,
                ];
            })
            ->values()
            ->all();
    }

    private function prefillFromReservation(Reservation $reservation): array
    {
        return [
            'reservation_id' => $reservation->id,
            'contract_number' => "CTR-{$reservation->reservation_number}",
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
        ];
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

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
