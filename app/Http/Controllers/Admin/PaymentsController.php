<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Support\BranchAccess;
use Inertia\Inertia;
use Inertia\Response;

class PaymentsController extends Controller
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
        $branchOptions = $this->branchAccess->availableBranchesForUser($user)
            ->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name])
            ->values();
        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;

        $paymentsQuery = Payment::query()
            ->with(['user:id,name,email,branch_id', 'reservation:id,reservation_number,car_id', 'reservation.car:id,branch_id', 'reservation.car.branch:id,name']);

        $this->applyPaymentBranchScope($paymentsQuery, $user, $branchId);

        $payments = $paymentsQuery
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('transaction_id', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('reservation', fn ($rq) => $rq->where('reservation_number', 'like', "%{$search}%"));
                });
            })
            ->when($status && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $payments->getCollection()->transform(function ($payment) {
            return [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'payment_method' => $payment->payment_method instanceof \BackedEnum ? $payment->payment_method->value : (string) $payment->payment_method,
                'status' => $payment->status instanceof \BackedEnum ? $payment->status->value : (string) $payment->status,
                'processed_at' => optional($payment->processed_at)->toDateTimeString(),
                'user' => $payment->user ? [
                    'id' => $payment->user->id,
                    'name' => $payment->user->name,
                    'email' => $payment->user->email,
                ] : null,
                'reservation' => $payment->reservation ? [
                    'id' => $payment->reservation->id,
                    'reservation_number' => $payment->reservation->reservation_number,
                ] : null,
                'branch_name' => $payment->reservation?->car?->branch?->name,
            ];
        });

        $statusCounts = [];
        foreach (PaymentStatus::cases() as $paymentStatus) {
            $statusQuery = Payment::query()->where('status', $paymentStatus->value);
            $this->applyPaymentBranchScope($statusQuery, $user, $branchId);
            $statusCounts[$paymentStatus->value] = $statusQuery->count();
        }

        $statuses = collect(PaymentStatus::cases())->mapWithKeys(function ($status) {
            return [
                $status->value => [
                    'label' => $status->label(),
                    'count' => 0,
                    'color' => $status->color(),
                ]
            ];
        })->map(function ($meta, $key) use ($statusCounts) {
            $meta['count'] = $statusCounts[$key] ?? 0;
            return $meta;
        })->toArray();

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'statuses' => $statuses,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
            ],
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
        ]);
    }

    private function applyPaymentBranchScope($query, $user, ?int $branchId): void
    {
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        if ($canAccessAllBranches) {
            if ($branchId) {
                $query->whereHas('reservation.car', fn ($carQuery) => $carQuery->where('branch_id', $branchId));
            }
            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('reservation.car', fn ($carQuery) => $carQuery->where('branch_id', $userBranchId));
    }
}
