<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\VaccinationSchedule;
use App\Services\Audit;
use App\Services\QueueTicketNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueApiController extends Controller
{
    /**
     * Public TV display — shows now serving + waiting list (no auth).
     */
    public function display(): JsonResponse
    {
        $nowServing = Ticket::with(['patient', 'assignedStaff', 'vaccinationSchedule'])
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

        $waiting = Ticket::with(['patient', 'vaccinationSchedule'])
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
        $data = $request->validate(['counter' => ['sometimes', 'integer', 'between:1,4']]);
        $counter = (int) ($data['counter'] ?? 1);
        [$next, $message] = DB::transaction(function () use ($counter) {
            $already = Ticket::where('counter_assigned', $counter)
                ->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->first();
            if ($already) return [null, ["Counter {$counter} is already serving {$already->ticket_number}.", 409]];
            $next = Ticket::where('status', 'waiting')->priorityFirst()->lockForUpdate()->first();
            if (!$next) return [null, ['No waiting tickets.', 404]];
            $next->update(['status' => 'called', 'counter_assigned' => $counter, 'assigned_staff_id' => auth()->id(), 'called_at' => now()]);
            return [$next, null];
        });
        if (!$next) return response()->json(['message' => $message[0]], $message[1]);

        Audit::log('queue.call_next', "API: Called next ticket {$next->ticket_number} to counter {$counter}", $next);

        return response()->json([
            'data' => $next->load('patient'),
            'message' => "Now serving {$next->ticket_number} at Counter {$counter}.",
        ]);
    }

    public function queueSchedule(VaccinationSchedule $schedule): JsonResponse
    {
        [$ticket, $message, $status] = DB::transaction(function () use ($schedule) {
            $lockedSchedule = VaccinationSchedule::lockForUpdate()->findOrFail($schedule->id);
            if (!in_array($lockedSchedule->status, ['scheduled', 'missed'], true)) {
                return [null, 'Only a scheduled or missed dose can be added to the queue.', 422];
            }
            if ($lockedSchedule->scheduled_date->gt(today())) {
                return [null, 'This dose is not due yet and cannot be queued.', 422];
            }
            if (VaccinationSchedule::where('patient_id', $lockedSchedule->patient_id)
                ->where('dose_number', '<', $lockedSchedule->dose_number)
                ->where('status', '!=', 'administered')->exists()) {
                return [null, 'Complete earlier doses before queueing this scheduled dose.', 422];
            }
            if (Ticket::where('vaccination_schedule_id', $lockedSchedule->id)
                ->whereIn('status', ['waiting', 'called', 'in_progress', 'held'])->exists()) {
                return [null, 'This dose already has an active queue ticket.', 409];
            }
            return [QueueTicketNumber::forReturningPatient($lockedSchedule->patient, $lockedSchedule), null, null];
        });
        if (!$ticket) return response()->json(['message' => $message], $status);
        Audit::log('queue.returning_patient', "API: Queued returning patient for dose {$schedule->dose_number} ({$ticket->ticket_number})", $ticket);

        return response()->json(['data' => $ticket->load(['patient', 'vaccinationSchedule']), 'message' => "Queued for dose {$schedule->dose_number}."], 201);
    }

    public function callSpecific(Ticket $ticket, Request $request): JsonResponse
    {
        $data = $request->validate(['counter' => ['sometimes', 'integer', 'between:1,4']]);
        $counter = (int) ($data['counter'] ?? $ticket->counter_assigned ?? 1);

        $message = DB::transaction(function () use ($ticket, $counter) {
            $locked = Ticket::lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status !== 'held') return "Only a held ticket can be recalled.";
            $active = Ticket::where('counter_assigned', $counter)->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->first();
            if ($active) return "Counter {$counter} is already serving {$active->ticket_number}.";
            $locked->update(['status' => 'called', 'counter_assigned' => $counter, 'assigned_staff_id' => auth()->id(), 'called_at' => now()]);
            return null;
        });
        if ($message) return response()->json(['message' => $message], 409);

        Audit::log('queue.call_specific', "API: Called ticket {$ticket->ticket_number} to counter {$counter}", $ticket);

        return response()->json([
            'data' => $ticket->load('patient'),
            'message' => "Called {$ticket->ticket_number} to Counter {$counter}.",
        ]);
    }

    public function serve(Ticket $ticket): JsonResponse
    {
        if (!in_array($ticket->status, ['called', 'in_progress'], true)) {
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
        if (auth()->user()->role === 'Doctor') {
            return response()->json(['message' => 'Doctors must record On Hold through the consultation form disposition.'], 409);
        }
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
        if (auth()->user()->role === 'Doctor') {
            return response()->json(['message' => 'Doctors must complete and confirm the consultation form before changing this ticket.'], 409);
        }
        if (!in_array($ticket->status, ['called', 'in_progress'], true)) {
            return response()->json(['message' => 'Only a called or currently serving ticket can be completed.'], 422);
        }
        if ($ticket->vaccination_schedule_id && $ticket->vaccinationSchedule?->status !== 'administered') {
            return response()->json(['message' => 'Administer the linked dose before completing this follow-up ticket.'], 422);
        }
        $ticket->update(['status' => 'completed', 'completed_at' => now()]);
        Audit::log('queue.complete', "API: Completed ticket {$ticket->ticket_number}", $ticket);

        return response()->json(['message' => "Ticket {$ticket->ticket_number} completed."]);
    }
}

