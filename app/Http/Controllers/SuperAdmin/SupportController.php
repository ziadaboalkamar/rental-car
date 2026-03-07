<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $tenantId = (int) $request->integer('tenant_id');

        $tickets = Ticket::query()
            ->where('channel', 'tenant')
            ->with(['tenant:id,name,slug,email', 'user:id,name,email'])
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($tenantId > 0, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhereHas('tenant', fn ($tq) => $tq->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->through(function (Ticket $ticket) {
                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status?->value ?? (string) $ticket->status,
                    'created_at' => $ticket->created_at,
                    'tenant' => $ticket->tenant ? [
                        'id' => $ticket->tenant->id,
                        'name' => $ticket->tenant->name,
                        'slug' => $ticket->tenant->slug,
                    ] : null,
                    'requester' => $ticket->user ? [
                        'name' => $ticket->user->name,
                        'email' => $ticket->user->email,
                    ] : null,
                ];
            })
            ->withQueryString();

        $tenantOptions = Ticket::query()
            ->where('channel', 'tenant')
            ->with('tenant:id,name')
            ->get()
            ->pluck('tenant')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn ($tenant) => ['id' => $tenant->id, 'name' => $tenant->name])
            ->all();

        $statuses = collect(TicketStatus::cases())->map(fn (TicketStatus $statusCase) => [
            'value' => $statusCase->value,
            'label' => $statusCase->label(),
            'color' => $statusCase->color(),
        ])->all();

        return Inertia::render('SuperAdmin/Support/Index', [
            'tickets' => $tickets,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'tenant_id' => $tenantId > 0 ? $tenantId : null,
            ],
            'statuses' => $statuses,
            'tenants' => $tenantOptions,
            'urls' => [
                'index' => route('superadmin.support.tenants.index'),
            ],
        ]);
    }

    public function show(Ticket $ticket): Response
    {
        $this->abortIfNotTenantSupport($ticket);

        $ticket->load([
            'tenant:id,name,slug,email',
            'user:id,name,email',
            'messages' => fn ($q) => $q->orderBy('created_at'),
            'messages.user:id,name,email,role',
        ]);

        return Inertia::render('SuperAdmin/Support/Show', [
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'status' => $ticket->status?->value ?? (string) $ticket->status,
                'created_at' => $ticket->created_at,
                'tenant' => $ticket->tenant ? [
                    'id' => $ticket->tenant->id,
                    'name' => $ticket->tenant->name,
                    'slug' => $ticket->tenant->slug,
                    'email' => $ticket->tenant->email,
                ] : null,
                'requester' => $ticket->user ? [
                    'id' => $ticket->user->id,
                    'name' => $ticket->user->name,
                    'email' => $ticket->user->email,
                ] : null,
                'messages' => $ticket->messages->map(fn ($message) => [
                    'id' => $message->id,
                    'message' => $message->message,
                    'user_id' => $message->user_id,
                    'user_name' => $message->user?->name ?? 'System',
                    'is_superadmin' => $message->user?->role?->value === 'super_admin',
                    'created_at' => $message->created_at,
                ])->values(),
            ],
            'urls' => [
                'index' => route('superadmin.support.tenants.index'),
                'reply' => route('superadmin.support.tenants.reply', ['ticket' => $ticket->id]),
                'close' => route('superadmin.support.tenants.close', ['ticket' => $ticket->id]),
            ],
        ]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->abortIfNotTenantSupport($ticket);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2'],
        ]);

        $ticket->messages()->create([
            'tenant_id' => $ticket->tenant_id,
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'is_admin' => true,
        ]);

        if (($ticket->status?->value ?? (string) $ticket->status) === TicketStatus::NEW->value) {
            $ticket->update(['status' => TicketStatus::IN_PROGRESS]);
        }

        return back()->with('success', 'Reply sent.');
    }

    public function close(Ticket $ticket): RedirectResponse
    {
        $this->abortIfNotTenantSupport($ticket);

        $ticket->update(['status' => TicketStatus::CLOSED]);

        return back()->with('success', 'Ticket closed.');
    }

    private function abortIfNotTenantSupport(Ticket $ticket): void
    {
        abort_unless($ticket->channel === 'tenant', 404);
    }
}

