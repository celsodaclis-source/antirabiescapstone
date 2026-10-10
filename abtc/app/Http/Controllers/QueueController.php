<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QueueController extends Controller
{
    public function index(): View
    {
        $nowServing = Ticket::with(['patient', 'assignedStaff'])
            ->whereIn('status', ['called', 'in_progress'])
            ->orderBy('called_at')
            ->get();

        $waiting = Ticket::with('patient')
            ->where('status', 'waiting')
            ->priorityFirst()
            ->get();

        $held = Ticket::with(['patient', 'assignedStaff'])
            ->where('status', 'held')
            ->orderBy('updated_at')
            ->get();

        $servedToday = Ticket::with(['patient', 'assignedStaff'])
            ->where('status', 'completed')
            ->whereDate('completed_at', now()->toDateString())
            ->orderByDesc('completed_at')
            ->get();

        // Counter status panel (counters 1-4)
        $counters = [];
        for ($i = 1; $i <= 4; $i++) {
            $active = $nowServing->firstWhere('counter_assigned', $i);
            $counters[$i] = $active
                ? ['status' => $active->status, 'ticket' => $active->ticket_number, 'staff' => $active->assignedStaff?->name, 'patient' => $active->patient?->fullName()]
                : ['status' => 'idle', 'ticket' => null, 'staff' => null, 'patient' => null];
        }

        return view('queue.index', compact('nowServing', 'waiting', 'held', 'servedToday', 'counters'));
    }

    public function data(): JsonResponse
    {
        $nowServing = Ticket::with(['patient', 'assignedStaff'])
            ->whereIn('status', ['called', 'in_progress'])
            ->orderBy('called_at')
            ->get();

        $waiting = Ticket::with('patient')
            ->where('status', 'waiting')
            ->priorityFirst()
            ->get();

        $held = Ticket::with(['patient', 'assignedStaff'])
            ->where('status', 'held')
            ->orderBy('updated_at')
            ->get();

        $servedToday = Ticket::with(['patient', 'assignedStaff'])
            ->where('status', 'completed')
            ->whereDate('completed_at', now()->toDateString())
            ->orderByDesc('completed_at')
            ->get();

        $counters = [];
        for ($i = 1; $i <= 4; $i++) {
            $active = $nowServing->firstWhere('counter_assigned', $i);
            $counters[$i] = $active
                ? ['status' => $active->status, 'ticket' => $active->ticket_number, 'staff' => $active->assignedStaff?->name, 'patient' => $active->patient?->fullName()]
                : ['status' => 'idle', 'ticket' => null, 'staff' => null, 'patient' => null];
        }

        return response()->json([
            'nowServing' => $nowServing,
            'waiting' => $waiting,
            'held' => $held,
            'servedToday' => $servedToday,
            'counters' => $counters,
            'serverTime' => now()->toIso8601String(),
        ]);
    }

    public function callNext(Request $request): RedirectResponse
    {
        $counter = (int) $request->input('counter', 1);

        // If a ticket is already being served at this counter, leave it.
        $already = Ticket::where('counter_assigned', $counter)
            ->whereIn('status', ['called', 'in_progress'])
            ->first();
        if ($already) {
            return back()->with('status', "Counter {$counter} is already serving {$already->ticket_number}.");
        }

        $next = Ticket::where('status', 'waiting')
            ->priorityFirst()
            ->first();

        if (! $next) {
            return back()->with('status', 'No waiting tickets in the queue.');
        }

        $next->update([
            'status' => 'called',
            'counter_assigned' => $counter,
            'assigned_staff_id' => auth()->id(),
            'called_at' => now(),
        ]);

        Audit::log('queue.call_next', "Called next ticket {$next->ticket_number} to counter {$counter}", $next);

        return back()->with('status', "Now serving {$next->ticket_number} at Counter {$counter}.");
    }

    public function callSpecific(Ticket $ticket, Request $request): RedirectResponse
    {
        $counter = (int) $request->input('counter', $ticket->counter_assigned ?? 1);

        if (! in_array($ticket->status, ['waiting', 'held'])) {
            return back()->with('status', "Ticket {$ticket->ticket_number} cannot be called (status: {$ticket->status}).");
        }

        $ticket->update([
            'status' => 'called',
            'counter_assigned' => $counter,
            'assigned_staff_id' => auth()->id(),
            'called_at' => now(),
        ]);

        Audit::log('queue.call_specific', "Called ticket {$ticket->ticket_number} to counter {$counter}", $ticket);

        return back()->with('status', "Called {$ticket->ticket_number} to Counter {$counter}.");
    }

    public function serve(Ticket $ticket): RedirectResponse
    {
        if (! in_array($ticket->status, ['called', 'in_progress', 'held'])) {
            return back()->with('status', 'Ticket must be called before serving.');
        }

        $ticket->update([
            'status' => 'in_progress',
            'served_at' => $ticket->served_at ?? now(),
        ]);

        Audit::log('queue.serve', "Started serving ticket {$ticket->ticket_number}", $ticket);

        return back()->with('status', "Serving {$ticket->ticket_number}.");
    }

    public function hold(Ticket $ticket): RedirectResponse
    {
        if (! in_array($ticket->status, ['waiting', 'called', 'in_progress'])) {
            return back()->with('status', 'Ticket cannot be held.');
        }

        $ticket->update([
            'status' => 'held',
            'counter_assigned' => null,
            'assigned_staff_id' => null,
        ]);

        Audit::log('queue.hold', "Held ticket {$ticket->ticket_number}", $ticket);

        return back()->with('status', "Ticket {$ticket->ticket_number} placed on hold.");
    }

    public function complete(Ticket $ticket): RedirectResponse
    {
        $ticket->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        Audit::log('queue.complete', "Completed ticket {$ticket->ticket_number}", $ticket);

        return back()->with('status', "Ticket {$ticket->ticket_number} completed.");
    }
}
