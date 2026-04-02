<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformSupportController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = (int) $request->user()->tenant_id;

        $tickets = Ticket::query()
            ->where('channel', 'tenant')
            ->where('tenant_id', $tenantId)
            ->with([
                'user:id,name,email',
                'messages' => fn ($q) => $q->latest()->limit(1),
            ])
            ->latest()
            ->paginate(10)
            ->through(function (Ticket $ticket) {
                $lastMessage = $ticket->messages->first();

                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status?->value ?? (string) $ticket->status,
                    'created_at' => $ticket->created_at,
                    'last_message' => $lastMessage?->message,
                    'last_message_at' => $lastMessage?->created_at,
                ];
            })
            ->withQueryString();

        return Inertia::render('Admin/SupportPlatform/Index', [
            'tickets' => $tickets,
            'statusOptions' => [
                ['value' => TicketStatus::NEW->value, 'label' => TicketStatus::NEW->label()],
                ['value' => TicketStatus::IN_PROGRESS->value, 'label' => TicketStatus::IN_PROGRESS->label()],
                ['value' => TicketStatus::CLOSED->value, 'label' => TicketStatus::CLOSED->label()],
            ],
            'urls' => [
                'index' => route('admin.support.platform.index'),
                'store' => route('admin.support.platform.store'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:2'],
        ]);

        $user = $request->user();
        $ticket = Ticket::create([
            'tenant_id' => $user->tenant_id,
            'channel' => 'tenant',
            'subject' => $validated['subject'],
            'status' => TicketStatus::NEW,
            'user_id' => $user->id,
        ]);

        $ticket->messages()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'message' => $validated['message'],
            'is_admin' => false,
        ]);

        return to_route('admin.support.platform.show', ['ticket' => $ticket->id])
            ->with('success', 'Support ticket created.');
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        $this->abortIfNotAccessible($request, $ticket);

        $ticket->load([
            'tenant:id,name,slug',
            'user:id,name,email',
            'assignedTo:id,name,email',
            'messages' => fn ($q) => $q->orderBy('created_at'),
            'messages.user:id,name,email,role',
        ]);

        return Inertia::render('Admin/SupportPlatform/Show', [
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
                ] : null,
                'requester' => $ticket->user ? [
                    'id' => $ticket->user->id,
                    'name' => $ticket->user->name,
                    'email' => $ticket->user->email,
                ] : null,
                'assigned_to' => $ticket->assignedTo ? [
                    'id' => $ticket->assignedTo->id,
                    'name' => $ticket->assignedTo->name,
                    'email' => $ticket->assignedTo->email,
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
                'index' => route('admin.support.platform.index'),
                'reply' => route('admin.support.platform.reply', ['ticket' => $ticket->id]),
                'close' => route('admin.support.platform.close', ['ticket' => $ticket->id]),
            ],
        ]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->abortIfNotAccessible($request, $ticket);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2'],
        ]);

        $ticket->messages()->create([
            'tenant_id' => $ticket->tenant_id,
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'is_admin' => false,
        ]);

        if (($ticket->status?->value ?? (string) $ticket->status) === TicketStatus::NEW->value) {
            $ticket->update(['status' => TicketStatus::IN_PROGRESS]);
        }

        return back()->with('success', 'Reply sent.');
    }

    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->abortIfNotAccessible($request, $ticket);

        $ticket->update(['status' => TicketStatus::CLOSED]);

        return back()->with('success', 'Ticket closed.');
    }

    private function abortIfNotAccessible(Request $request, Ticket $ticket): void
    {
        abort_unless(
            $ticket->channel === 'tenant' && (int) $ticket->tenant_id === (int) $request->user()->tenant_id,
            403
        );
    }
}
