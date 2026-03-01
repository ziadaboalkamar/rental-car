<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Enums\ReservationStatus;
use App\Enums\PaymentStatus;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Support\BranchAccess;

class ReservationsController extends Controller
{
    public function __construct(private BranchAccess $branchAccess)
    {
    }

    /**
     * Display a listing of reservations.
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

        // Status counts for filter chips
        $statusCountsQuery = Reservation::query()->selectRaw('status, count(*) as count');
        $this->applyReservationBranchScope($statusCountsQuery, $user, $branchId);
        $statusCounts = $statusCountsQuery
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $reservationsQuery = Reservation::query()
            ->with([
                'user:id,name,email',
                'car:id,branch_id,make,model,year,license_plate',
                'car.branch:id,name',
            ]);

        $this->applyReservationBranchScope($reservationsQuery, $user, $branchId);

        $reservations = $reservationsQuery
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reservation_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('car', function ($cq) use ($search) {
                            $cq->where('make', 'like', "%{$search}%")
                                ->orWhere('model', 'like', "%{$search}%")
                                ->orWhere('license_plate', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status && $status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $reservations->getCollection()->transform(function (Reservation $reservation) {
            return [
                'id' => $reservation->id,
                'reservation_number' => $reservation->reservation_number,
                'user' => $reservation->user ? [
                    'id' => $reservation->user->id,
                    'name' => $reservation->user->name,
                    'email' => $reservation->user->email,
                ] : null,
                'car' => $reservation->car ? [
                    'id' => $reservation->car->id,
                    'make' => $reservation->car->make,
                    'model' => $reservation->car->model,
                    'year' => $reservation->car->year,
                    'license_plate' => $reservation->car->license_plate,
                ] : null,
                'branch_name' => $reservation->car?->branch?->name,
                'start_date' => optional($reservation->start_date)->toDateString(),
                'end_date' => optional($reservation->end_date)->toDateString(),
                'total_days' => $reservation->total_days,
                'total_amount' => $reservation->total_amount,
                'status' => $reservation->status instanceof ReservationStatus
                    ? $reservation->status->value
                    : (string) $reservation->status,
            ];
        });

        $statuses = collect(ReservationStatus::cases())->mapWithKeys(function ($st) use ($statusCounts) {
            $meta = ReservationStatus::getMeta();
            $statusMeta = collect($meta)->firstWhere('value', $st->value);
            
            return [
                $st->value => [
                    'label' => $statusMeta['label'] ?? ucfirst(str_replace('_', ' ', $st->value)),
                    'count' => $statusCounts[$st->value] ?? 0,
                    'color' => $statusMeta['color'] ?? '#6B7280',
                ],
            ];
        })->toArray();

        return Inertia::render('Admin/Reservations/Index', [
            'reservations' => $reservations,
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
     * Display the specified reservation details.
     */
    public function show(Reservation $reservation): Response
    {
        abort_unless($this->canAccessReservation($reservation, request()->user()), 403);
        $reservation->load(['user', 'car', 'payments']);

        return Inertia::render('Admin/Reservations/Show', [
            'reservation' => $reservation,
            'statusMeta' => ReservationStatus::getMeta(),
            'paymentStatusMeta' => PaymentStatus::getMeta(),
        ]);
    }

    /**
     * Show the form for editing the specified reservation.
     */
    public function edit(Reservation $reservation): Response
    {
        abort_unless($this->canAccessReservation($reservation, request()->user()), 403);
        $reservation->load(['user:id,name,email', 'car:id,make,model,year,license_plate']);

        return Inertia::render('Admin/Reservations/Edit', [
            'reservation' => $reservation,
            'enums' => [
                'statuses' => ReservationStatus::getMeta(),
            ],
        ]);
    }

    /**
     * Update the specified reservation in storage.
     */
    public function update(Request $request, Reservation $reservation)
    {
        abort_unless($this->canAccessReservation($reservation, $request->user()), 403);
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'return_time' => ['nullable', 'date_format:H:i'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'return_location' => ['nullable', 'string', 'max:255'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::enum(ReservationStatus::class)],
            'cancellation_reason' => ['nullable', 'string'],
        ]);

        if (config('app.demo_mode')) {
            return redirect()
                ->back()
                ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');
        }

        $reservation->fill($validated);

        // Recalculate totals when dates or discount change
        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $totalDays = $start->diffInDays($end) + 1;
        $reservation->total_days = $totalDays;
        $reservation->subtotal = $reservation->daily_rate * $totalDays;
        $reservation->tax_amount = round($reservation->subtotal * 0.21, 2);
        $reservation->total_amount = $reservation->subtotal + $reservation->tax_amount - (float)($reservation->discount_amount ?? 0);

        // Maintain cancellation metadata
        if ($reservation->status === ReservationStatus::CANCELLED && !$reservation->cancelled_at) {
            $reservation->cancelled_at = now();
        }
        if ($reservation->status !== ReservationStatus::CANCELLED) {
            $reservation->cancellation_reason = null;
            $reservation->cancelled_at = null;
        }

        $reservation->save();

        return redirect()
            ->route('admin.reservations.show', [
                'subdomain' => $request->route('subdomain'),
                'reservation' => $reservation->id,
            ])
            ->with('success', 'Reservation updated successfully.');
    }

    /**
     * Generate and download a PDF for the reservation details.
     */
    public function print(Reservation $reservation)
    {
        abort_unless($this->canAccessReservation($reservation, request()->user()), 403);
        $reservation->load(['user', 'car', 'payments']);

        $pdf = Pdf::loadView('admin.reservations.print', [
            'reservation' => $reservation,
            'statusMeta' => ReservationStatus::getMeta(),
            'paymentStatusMeta' => PaymentStatus::getMeta(),
            'currency' => config('app.currency_symbol'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($reservation->reservation_number . '.pdf');
    }

    private function applyReservationBranchScope($query, $user, ?int $branchId): void
    {
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        if ($canAccessAllBranches) {
            if ($branchId) {
                $query->whereHas('car', fn ($carQuery) => $carQuery->where('branch_id', $branchId));
            }

            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('car', fn ($carQuery) => $carQuery->where('branch_id', $userBranchId));
    }

    private function canAccessReservation(Reservation $reservation, $user): bool
    {
        $reservation->loadMissing('car:id,branch_id');

        return $this->branchAccess->canAccessBranchId($user, $reservation->car?->branch_id ? (int) $reservation->car->branch_id : null);
    }
}
