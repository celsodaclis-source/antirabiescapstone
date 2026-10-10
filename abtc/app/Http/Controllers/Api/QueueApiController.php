<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueApiController extends Controller
{
    /**
     * Public TV display — shows now serving + waiting list (no auth).
     */
    public function display(): JsonResponse
    {
        $nowServing = Ticket::with(['patient', 'assignedStaff'])
            ->whereIn('status', ['called', 'in_progress'])
            ->orderBy('called_at')
            ->get()
            ->map(fn ($t) => [
                'ticket_number' => $t->ticket_number,
                'patient_name' => $t->patient?->fullName(),
                'counter' => $t->counter_assigned,
                'status' => $t->status,
                'is_priority' => $t->is_priority,
            ]);

        $waiting = Ticket::with('patient')
            ->where('status', 'waiting')
            ->priorityFirst()
            ->get()
            ->map(fn ($t) => [
                'ticket_number' => $t->ticket_number,
                'patient_name' => $t->patient?->fullName(),
                'is_priority' => $t->is_priority,
                'waiting_since' => $t->created_at->diffForHumans(),
            ]);

        $completedToday = Ticket::where('status', 'completed')
            ->whereDate('completed_at', now()->toDateString())
            ->count();

        return response()->json([
            'now_serving' => $nowServing,
            'waiting' => $waiting,
            'waiting_count' => $waiting->count(),
            'completed_today' => $completedToday,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Public counter status (TV display).
     */
    public function counters(): JsonResponse
    {
        $serving = Ticket::with(['patient', 'assignedStaff'])
            ->whereIn('status', ['called', 'in_progress'])
            ->orderBy('called_at')
            ->get();

        $counters = [];
        for ($i = 1; $i <= 4; $i++) {
            $active = $serving->firstWhere('counter_assigned', $i);
            $counters["counter_{$i}"] = $active
                ? [
                    'status' => $active->status === 'in_progress' ? 'serving' : 'calling',
                    'ticket' => $active->ticket_number,
                    'patient' => $active->patient?->fullName(),
                    'staff' => $active->assignedStaff?->name,
                ]
                : ['status' => 'idle', 'ticket' => null, 'patient' => null, 'staff' => null];
        }

        return response()->json(['counters' => $counters]);
    }

    /**
     * Get waiting list (authenticated).
     */
    public function waiting(): JsonResponse
    {
        $waiting = Ticket::with('patient')
            ->where('status', 'waiting')
            ->priorityFirst()
            ->get();

        return response()->json(['data' => $waiting]);
    }

    /**
     * Get currently serving (authenticated).
     */
    public function serving(): JsonResponse
    {
        $serving = Ticket::with(['patient', 'assignedStaff'])
            ->whereIn('status', ['called', 'in_progress'])
            ->orderBy('called_at')
            ->get();

        return response()->json(['data' => $serving]);
    }

    public function callNext(Request $request): JsonResponse
    {
        $counter = (int) $request->input('counter', 1);

        $already = Ticket::where('counter_assigned', $counter)
            ->whereIn('status', ['called', 'in_progress'])
            ->first();
        if ($already) {
            return response()->json(['message' => "Counter {$counter} is already serving {$already->ticket_number}."], 409);
        }

        $next = Ticket::where('status', 'waiting')->priorityFirst()->first();
        if (!$next) {
            return response()->json(['message' => 'No waiting tickets.'], 404);
        }

        $next->update([
            'status' => 'called',
            'counter_assigned' => $counter,
            'assigned_staff_id' => auth()->id(),
            'called_at' => now(),
        ]);

        Audit::log('queue.call_next', "API: Called next ticket {$next->ticket_number} to counter {$counter}", $next);

        return response()->json([
            'data' => $next->load('patient'),
            'message' => "Now serving {$next->ticket_number} at Counter {$counter}.",
        ]);
    }

    public function callSpecific(Ticket $ticket, Request $request): JsonResponse
    {
        $counter = (int) $request->input('counter', $ticket->counter_assigned ?? 1);

        if (!in_array($ticket->status, ['waiting', 'held'])) {
            return response()->json(['message' => "Ticket {$ticket->ticket_number} cannot be called."], 422);
        }

        $ticket->update([
            'status' => 'called',
            'counter_assigned' => $counter,
            'assigned_staff_id' => auth()->id(),
            'called_at' => now(),
        ]);

        Audit::log('queue.call_specific', "API: Called ticket {$ticket->ticket_number} to counter {$counter}", $ticket);

        return response()->json([
            'data' => $ticket->load('patient'),
            'message' => "Called {$ticket->ticket_number} to Counter {$counter}.",
        ]);
    }

    public function serve(Ticket $ticket): JsonResponse
    {
        if (!in_array($ticket->status, ['called', 'in_progress', 'held'])) {
            return response()->json(['message' => 'Ticket must be called first.'], 422);
        }

        $ticket->update([
            'status' => 'in_progress',
            'served_at' => $ticket->served_at ?? now(),
        ]);

        Audit::log('queue.serve', "API: Started serving ticket {$ticket->ticket_number}", $ticket);

        return response()->json(['data' => $ticket->load('patient'), 'message' => "Serving {$ticket->ticket_number}."]);
    }

    public function hold(Ticket $ticket): JsonResponse
    {
        if (!in_array($ticket->status, ['waiting', 'called', 'in_progress'])) {
            return response()->json(['message' => 'Ticket cannot be held.'], 422);
        }

        $ticket->update([
            'status' => 'held',
            'counter_assigned' => null,
            'assigned_staff_id' => null,
        ]);

        Audit::log('queue.hold', "API: Held ticket {$ticket->ticket_number}", $ticket);

        return response()->json(['message' => "Ticket {$ticket->ticket_number} placed on hold."]);
    }

    public function complete(Ticket $ticket): JsonResponse
    {
        $ticket->update(['status' => 'completed', 'completed_at' => now()]);
        Audit::log('queue.complete', "API: Completed ticket {$ticket->ticket_number}", $ticket);

        return response()->json(['message' => "Ticket {$ticket->ticket_number} completed."]);
    }
}

