<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CarStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\BranchAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private BranchAccess $branchAccess)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        $branchOptions = $this->branchAccess
            ->availableBranchesForUser($user)
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])
            ->values();

        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;

        // ── KPI Stats ────────────────────────────────────────────────
        $carsQuery = Car::query();
        $this->applyCarBranchScope($carsQuery, $user, $branchId);

        $totalCars      = (clone $carsQuery)->count();
        $availableCars  = (clone $carsQuery)->where('status', CarStatus::AVAILABLE)->count();

        $reservationsQuery = Reservation::query();
        $this->applyReservationBranchScope($reservationsQuery, $user, $branchId);

        $activeReservations  = (clone $reservationsQuery)->where('status', ReservationStatus::ACTIVE)->count();
        $pendingReservations = (clone $reservationsQuery)->where('status', ReservationStatus::PENDING)->count();
        $totalReservations   = (clone $reservationsQuery)->count();

        $paymentsQuery = Payment::query()->where('status', PaymentStatus::COMPLETED);
        $this->applyPaymentBranchScope($paymentsQuery, $user, $branchId);
        $totalRevenue = (clone $paymentsQuery)->sum('amount');

        $totalClients = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'client'))
            ->count();

        // ── Reservations by Status ────────────────────────────────────
        $reservationsByStatus = collect(ReservationStatus::cases())->map(function ($status) use ($reservationsQuery) {
            return [
                'status' => $status->value,
                'label'  => ucfirst(str_replace('_', ' ', $status->value)),
                'count'  => (clone $reservationsQuery)->where('status', $status->value)->count(),
                'color'  => ReservationStatus::statusColors()[$status->value] ?? '#6B7280',
            ];
        })->values();

        // ── Fleet Status ──────────────────────────────────────────────
        $fleetStatus = collect(CarStatus::cases())->map(function ($status) use ($carsQuery) {
            return [
                'status' => $status->value,
                'label'  => $status->label(),
                'count'  => (clone $carsQuery)->where('status', $status->value)->count(),
                'color'  => $status->color(),
            ];
        })->values();

        // ── Monthly Revenue (last 6 months) ───────────────────────────
        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->startOfMonth()->subMonths($i);
            $q = Payment::query()
                ->where('status', PaymentStatus::COMPLETED)
                ->whereBetween('processed_at', [
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth(),
                ]);
            $this->applyPaymentBranchScope($q, $user, $branchId);

            $monthlyRevenue[] = [
                'month'   => $month->format('M Y'),
                'revenue' => (float) $q->sum('amount'),
            ];
        }

        // ── Recent Reservations (last 5) ──────────────────────────────
        $recentReservations = (clone $reservationsQuery)
            ->with(['user:id,name,email', 'car:id,make,model,year,branch_id', 'car.branch:id,name'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Reservation $r) => [
                'id'                 => $r->id,
                'reservation_number' => $r->reservation_number,
                'client_name'        => $r->user?->name,
                'car_name'           => $r->car ? "{$r->car->year} {$r->car->make} {$r->car->model}" : '—',
                'branch_name'        => $r->car?->branch?->name ?? '—',
                'start_date'         => optional($r->start_date)->toDateString(),
                'end_date'           => optional($r->end_date)->toDateString(),
                'total_amount'       => (float) $r->total_amount,
                'status'             => $r->status instanceof ReservationStatus
                    ? $r->status->value
                    : (string) $r->status,
                'status_color'       => ReservationStatus::statusColors()[$r->status instanceof ReservationStatus ? $r->status->value : (string) $r->status] ?? '#6B7280',
            ]);

        // ── Top Cars (by completed reservation count, top 5) ──────────
        $topCars = Car::query()
            ->select('cars.id', 'cars.make', 'cars.model', 'cars.year', 'cars.price_per_day', 'cars.status')
            ->withCount([
                'reservations as completed_count' => fn ($q) => $q->where('status', ReservationStatus::COMPLETED),
            ])
            ->when(!$canAccessAllBranches && $user?->branch_id, fn ($q) => $q->where('branch_id', (int) $user->branch_id))
            ->when($canAccessAllBranches && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('completed_count')
            ->limit(5)
            ->get()
            ->map(fn (Car $car) => [
                'id'             => $car->id,
                'name'           => "{$car->year} {$car->make} {$car->model}",
                'price_per_day'  => (float) $car->price_per_day,
                'status'         => $car->status instanceof CarStatus ? $car->status->value : (string) $car->status,
                'status_label'   => $car->status instanceof CarStatus ? $car->status->label() : (string) $car->status,
                'status_color'   => $car->status instanceof CarStatus ? $car->status->color() : '#6B7280',
                'completed_count'=> $car->completed_count,
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_cars'           => $totalCars,
                'available_cars'       => $availableCars,
                'active_reservations'  => $activeReservations,
                'pending_reservations' => $pendingReservations,
                'total_reservations'   => $totalReservations,
                'total_revenue'        => (float) $totalRevenue,
                'total_clients'        => $totalClients,
            ],
            'reservationsByStatus' => $reservationsByStatus,
            'fleetStatus'          => $fleetStatus,
            'monthlyRevenue'       => $monthlyRevenue,
            'recentReservations'   => $recentReservations,
            'topCars'              => $topCars,
            'branches'             => $branchOptions,
            'filters'              => ['branch_id' => $branchId],
            'canAccessAllBranches' => $canAccessAllBranches,
        ]);
    }

    // ── Branch scope helpers ──────────────────────────────────────────

    private function applyCarBranchScope($query, $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
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

    private function applyReservationBranchScope($query, $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->whereHas('car', fn ($q) => $q->where('branch_id', $branchId));
            }
            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('car', fn ($q) => $q->where('branch_id', $userBranchId));
    }

    private function applyPaymentBranchScope($query, $user, ?int $branchId): void
    {
        if ($this->branchAccess->canAccessAllBranches($user)) {
            if ($branchId) {
                $query->whereHas('reservation.car', fn ($q) => $q->where('branch_id', $branchId));
            }
            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('reservation.car', fn ($q) => $q->where('branch_id', $userBranchId));
    }
}
