<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
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
        $ticketType = $request->string('type', 'customer')->toString();
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));
        $branchOptions = $this->branchAccess->availableBranchesForUser($user)
            ->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name])
            ->values();
        $allowedBranchIds = $branchOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchId = ($requestedBranchId && in_array($requestedBranchId, $allowedBranchIds, true))
            ? $requestedBranchId
            : null;

        $query = Ticket::query()
            ->when($ticketType === 'customer', function ($q) {
                $q->whereNotNull('user_id')
                  ->with('user:id,name,email,branch_id')
                  ->with('user.branch:id,name');
            }, function ($q) {
                $q->whereNull('user_id');
            })
            ->tap(function ($q) use ($user, $branchId, $ticketType) {
                $this->applyTicketBranchScope($q, $user, $branchId, $ticketType);
            })
            ->when($search, function ($q) use ($search, $ticketType) {
                $q->where(function ($w) use ($search, $ticketType) {
                    $w->where('subject', 'like', "%{$search}%");
                    
                    if ($ticketType === 'customer') {
                        $w->orWhereHas('user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%");
                        });
                    } else {
                        $w->orWhere('guest_name', 'like', "%{$search}%")
                          ->orWhere('guest_email', 'like', "%{$search}%");
                    }
                });
            })
            ->when($status && $status !== 'all', function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->latest();

        $tickets = $query->paginate(10)->withQueryString();
        $tickets->getCollection()->transform(function ($ticket) {
            $ticket->branch_name = $ticket->user?->branch?->name;
            return $ticket;
        });

        // Get status counts for both ticket types
        $statusCounts = [
            'customer' => [
                'all' => $this->ticketCountForType('customer', null, $user, $branchId),
                ...collect(TicketStatus::cases())->mapWithKeys(fn($status) => [
                    $status->value => $this->ticketCountForType('customer', $status->value, $user, $branchId)
                ])->toArray()
            ],
            'guest' => [
                'all' => $this->ticketCountForType('guest', null, $user, $branchId),
                ...collect(TicketStatus::cases())->mapWithKeys(fn($status) => [
                    $status->value => $this->ticketCountForType('guest', $status->value, $user, $branchId)
                ])->toArray()
            ]
        ];

        $statuses = collect(TicketStatus::cases())->mapWithKeys(function ($status) {
            return [
                $status->value => [
                    'label' => $status->label(),
                    'color' => $status->color(),
                ]
            ];
        })->toArray();

        return Inertia::render('Admin/Support/Index', [
            'tickets' => $tickets,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'type' => $ticketType,
                'branch_id' => $branchId,
            ],
            'statuses' => $statuses,
            'statusCounts' => $statusCounts,
            'branches' => $branchOptions,
            'canAccessAllBranches' => $canAccessAllBranches,
        ]);
    }

    // In SupportController.php

    public function show(Ticket $ticket)
    {
        abort_unless($this->canAccessTicket($ticket, request()->user()), 403);
        // Eager load the messages and user relationship
        $ticket->load(['messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }, 'user']);

        return Inertia::render('Admin/Support/Show', [
            'ticket' => $ticket,
            'isGuest' => is_null($ticket->user_id),
        ]);
    }

    public function reply(Request $request, Ticket $ticket)
    {
        abort_unless($this->canAccessTicket($ticket, $request->user()), 403);
        $request->validate([
            'message' => 'required|string|min:1',
        ]);

        // Only allow replies to customer tickets
        if (is_null($ticket->user_id)) {
            return back()->with('error', 'Cannot reply to guest tickets');
        }

        $ticket->messages()->create([
            'tenant_id' => $ticket->tenant_id,
            'user_id' => auth()->id(),
            'message' => $request->message,
            'is_admin' => true,
        ]);

        $ticket->update([
            'status' => TicketStatus::IN_PROGRESS,
        ]);


        return back()->with('success', 'Reply sent successfully');
    }

    public function close(Ticket $ticket)
    {
        abort_unless($this->canAccessTicket($ticket, request()->user()), 403);
        $ticket->update([
            'status' => TicketStatus::CLOSED,
        ]);

        return redirect()->route('admin.support.index');
    }

    private function ticketCountForType(string $type, ?string $status, $user, ?int $branchId): int
    {
        $query = Ticket::query();
        if ($type === 'customer') {
            $query->whereNotNull('user_id');
        } else {
            $query->whereNull('user_id');
        }
        if ($status) {
            $query->where('status', $status);
        }
        $this->applyTicketBranchScope($query, $user, $branchId, $type);
        return $query->count();
    }

    private function applyTicketBranchScope($query, $user, ?int $branchId, string $ticketType): void
    {
        $canAccessAllBranches = $this->branchAccess->canAccessAllBranches($user);

        // Guest tickets are tenant-wide and have no branch link. Only tenant owner should see them.
        if ($ticketType !== 'customer') {
            if (!$canAccessAllBranches) {
                $query->whereRaw('1 = 0');
            }
            return;
        }

        if ($canAccessAllBranches) {
            if ($branchId) {
                $query->whereHas('user', fn ($uq) => $uq->where('branch_id', $branchId));
            }
            return;
        }

        $userBranchId = (int) ($user?->branch_id ?? 0);
        if ($userBranchId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('user', fn ($uq) => $uq->where('branch_id', $userBranchId));
    }

    private function canAccessTicket(Ticket $ticket, $user): bool
    {
        $ticket->loadMissing('user:id,branch_id');

        if (is_null($ticket->user_id)) {
            return $this->branchAccess->canAccessAllBranches($user);
        }

        return $this->branchAccess->canAccessBranchId($user, $ticket->user?->branch_id ? (int) $ticket->user->branch_id : null);
    }
}
