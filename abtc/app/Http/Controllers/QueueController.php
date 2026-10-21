<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\DoctorStation;
use App\Models\Patient;
use App\Models\VaccinationSchedule;
use App\Services\Audit;
use App\Services\QueueTicketNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QueueController extends Controller
{
    public function queueSchedule(VaccinationSchedule $schedule): RedirectResponse
    {
        [$ticket, $message] = DB::transaction(function () use ($schedule) {
            $lockedSchedule = VaccinationSchedule::lockForUpdate()->findOrFail($schedule->id);
            if (! in_array($lockedSchedule->status, ['scheduled', 'missed'], true)) {
                return [null, 'Only a scheduled or missed dose can be added to the queue.'];
            }
            if ($lockedSchedule->scheduled_date->gt(today())) {
                return [null, 'This dose is not due yet and cannot be queued.'];
            }
            if (VaccinationSchedule::where('patient_id', $lockedSchedule->patient_id)
                ->where('dose_number', '<', $lockedSchedule->dose_number)
                ->where('status', '!=', 'administered')->exists()) {
                return [null, 'Complete earlier doses before queueing this scheduled dose.'];
            }
            $activeTicket = Ticket::where('vaccination_schedule_id', $lockedSchedule->id)
                ->whereIn('status', ['waiting', 'called', 'in_progress', 'held'])->exists();
            if ($activeTicket) return [null, 'This dose already has an active queue ticket.'];

            return [QueueTicketNumber::forReturningPatient($lockedSchedule->patient, $lockedSchedule), null];
        });
        if (! $ticket) return back()->with('status', $message);
        Audit::log('queue.returning_patient', "Queued returning patient for dose {$schedule->dose_number} ({$ticket->ticket_number})", $ticket);

        return redirect()->route('schedules.index')->with('status', "Returning patient queued as {$ticket->ticket_number} for dose {$schedule->dose_number}.");
    }

    public function index(): View
    {
        $nowServing = Ticket::with(['patient', 'assignedStaff', 'vaccinationSchedule'])
            ->whereIn('status', ['called', 'in_progress'])
            ->orderBy('called_at')
            ->get();

        $waiting = Ticket::with(['patient', 'vaccinationSchedule'])
            ->where('status', 'waiting')
            ->priorityFirst()
            ->get();

        $held = Ticket::with(['patient', 'assignedStaff', 'vaccinationSchedule'])
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
        $nowServing = Ticket::with(['patient', 'assignedStaff', 'vaccinationSchedule'])
            ->whereIn('status', ['called', 'in_progress'])
            ->orderBy('called_at')
            ->get();

        $waiting = Ticket::with(['patient', 'vaccinationSchedule'])
            ->where('status', 'waiting')
            ->priorityFirst()
            ->get();

        $held = Ticket::with(['patient', 'assignedStaff', 'vaccinationSchedule'])
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
        $data = $request->validate(['counter' => ['sometimes', 'integer', 'between:1,4']]);
        $counter = (int) ($data['counter'] ?? 1);
        $actor = $request->user();

        [$next, $message] = DB::transaction(function () use ($counter, $actor) {
            $station = null;
            if ($actor->role === 'Doctor') {
                $station = DoctorStation::where('user_id', $actor->id)->lockForUpdate()->first();
                if (! $station) return [null, 'Open an assigned doctor station before calling a patient.'];
                $counter = $station->id;

                $activeForDoctor = Ticket::where('assigned_staff_id', $actor->id)
                    ->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->first();
                if ($activeForDoctor) return [null, "Complete or resolve {$activeForDoctor->ticket_number} before calling another patient."];
            }

            $already = Ticket::where('counter_assigned', $counter)
                ->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->first();
            if ($already) return [null, "Counter {$counter} is already serving {$already->ticket_number}."];

            $nextQuery = Ticket::where('status', 'waiting');
            if ($actor->role === 'Doctor') {
                $nextQuery->where(function ($query) {
                    $query->where(function ($consultation) {
                        $consultation->whereIn('service_stage', ['assessment', 'medical_review'])
                            ->whereNotNull('assessment_completed_at');
                    })->orWhere('service_stage', 'follow_up');
                })->whereDoesntHave('patient.tickets', function ($query) {
                    $query->whereIn('status', ['called', 'in_progress']);
                });
            }

            $next = $nextQuery->priorityFirst()->lockForUpdate()->first();
            if (! $next) return [null, 'No waiting tickets in the queue.'];

            if ($actor->role === 'Doctor') {
                Patient::whereKey($next->patient_id)->lockForUpdate()->firstOrFail();
                if (Ticket::where('patient_id', $next->patient_id)->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->exists()) {
                    return [null, 'This patient already has an active consultation with a doctor.'];
                }
            }

            $next->update([
                'status' => 'called', 'counter_assigned' => $counter,
                'assigned_staff_id' => auth()->id(), 'called_at' => now(),
                'doctor_station_id' => $station?->id,
            ]);
            return [$next, null];
        });
        if (! $next) return back()->with('status', $message);

        Audit::log('queue.call_next', "Called next ticket {$next->ticket_number} to counter {$counter}", $next);

        return back()->with('status', "Now serving {$next->ticket_number} at Counter {$counter}.");
    }

    public function callSpecific(Ticket $ticket, Request $request): RedirectResponse
    {
        $data = $request->validate(['counter' => ['sometimes', 'integer', 'between:1,4']]);
        $counter = (int) ($data['counter'] ?? $ticket->counter_assigned ?? 1);
        $actor = $request->user();

        $message = DB::transaction(function () use ($ticket, $counter, $actor) {
            $station = null;
            if ($actor->role === 'Doctor') {
                $station = DoctorStation::where('user_id', $actor->id)->lockForUpdate()->first();
                if (! $station) return 'Open an assigned doctor station before calling a patient.';
                $counter = $station->id;
            }
            $lockedTicket = Ticket::lockForUpdate()->findOrFail($ticket->id);
            if ($lockedTicket->status !== 'held') {
                return "Ticket {$lockedTicket->ticket_number} cannot be called (status: {$lockedTicket->status}).";
            }
            if ($actor->role === 'Doctor') {
                if ($lockedTicket->service_stage !== 'follow_up' && ! $lockedTicket->assessment_completed_at) {
                    return 'A nurse must complete the patient assessment before the doctor can call this ticket.';
                }
                $activeForDoctor = Ticket::where('assigned_staff_id', $actor->id)
                    ->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->first();
                if ($activeForDoctor) return "Complete or resolve {$activeForDoctor->ticket_number} before calling another patient.";

                Patient::whereKey($lockedTicket->patient_id)->lockForUpdate()->firstOrFail();
                if (Ticket::where('patient_id', $lockedTicket->patient_id)->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->exists()) {
                    return 'This patient already has an active consultation with a doctor.';
                }
            }
            $already = Ticket::where('counter_assigned', $counter)
                ->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->first();
            if ($already) return "Counter {$counter} is already serving {$already->ticket_number}.";

            $lockedTicket->update([
                'status' => 'called', 'counter_assigned' => $counter,
                'assigned_staff_id' => auth()->id(), 'called_at' => now(),
                'doctor_station_id' => $station?->id,
            ]);
            return null;
        });
        if ($message) return back()->with('status', $message);

        Audit::log('queue.call_specific', "Called ticket {$ticket->ticket_number} to counter {$counter}", $ticket);

        return back()->with('status', "Called {$ticket->ticket_number} to Counter {$counter}.");
    }

    public function serve(Ticket $ticket): RedirectResponse
    {
        $message = DB::transaction(function () use ($ticket) {
            $station = null;
            if (auth()->user()->role === 'Doctor') {
                $station = DoctorStation::where('user_id', auth()->id())->lockForUpdate()->first();
                if (! $station) return 'Open an assigned doctor station before serving a patient.';
            }
            $lockedTicket = Ticket::lockForUpdate()->findOrFail($ticket->id);
            if (! in_array($lockedTicket->status, ['called', 'in_progress'], true)) {
                return 'Ticket must be called before serving.';
            }
            if (auth()->user()->role === 'Doctor') {
                if (! $station || $lockedTicket->assigned_staff_id !== auth()->id() || $lockedTicket->doctor_station_id !== $station->id) {
                    return 'This ticket is not assigned to your active doctor station.';
                }
            }

            $lockedTicket->update([
                'status' => 'in_progress',
                'served_at' => $lockedTicket->served_at ?? now(),
            ]);
            return null;
        });
        if ($message) return back()->withErrors(['ticket' => $message]);

        Audit::log('queue.serve', "Started serving ticket {$ticket->ticket_number}", $ticket);

        return back()->with('status', "Serving {$ticket->ticket_number}.");
    }

    public function hold(Ticket $ticket): RedirectResponse
    {
        if (! in_array($ticket->status, ['waiting', 'called', 'in_progress'])) {
            return back()->with('status', 'Ticket cannot be held.');
        }
        if (auth()->user()->role === 'Doctor'
            && ($ticket->assigned_staff_id !== auth()->id() || ! $ticket->doctor_station_id
                || ! DoctorStation::where('id', $ticket->doctor_station_id)->where('user_id', auth()->id())->exists())) {
            return back()->withErrors(['ticket' => 'This ticket is not assigned to your active doctor station.']);
        }

        $ticket->update([
            'status' => 'held',
            'counter_assigned' => null,
            'assigned_staff_id' => null,
            'doctor_station_id' => null,
        ]);

        Audit::log('queue.hold', "Held ticket {$ticket->ticket_number}", $ticket);

        return back()->with('status', "Ticket {$ticket->ticket_number} placed on hold.");
    }

    public function complete(Ticket $ticket): RedirectResponse
    {
        $doctor = auth()->user()->role === 'Doctor';
        $message = DB::transaction(function () use ($ticket, $doctor) {
            $station = $doctor ? DoctorStation::where('user_id', auth()->id())->lockForUpdate()->first() : null;
            $lockedTicket = Ticket::with(['vaccinationSchedule', 'patient.biteIncidents', 'patient.vaccinationSchedules'])
                ->lockForUpdate()->findOrFail($ticket->id);
            if (! in_array($lockedTicket->status, $doctor ? ['in_progress'] : ['called', 'in_progress'], true)) {
                return $lockedTicket->status === 'completed'
                    ? 'This queue ticket has already been completed.'
                    : 'Only a currently serving ticket can be completed.';
            }
            if ($doctor) {
                $stationMatches = $station && $lockedTicket->assigned_staff_id === auth()->id()
                    && ($lockedTicket->doctor_station_id === $station->id
                        || ($lockedTicket->doctor_station_id === null && $lockedTicket->counter_assigned === $station->id));
                if (! $stationMatches) return 'This ticket is not assigned to your active doctor station.';

                if ($lockedTicket->service_stage !== 'follow_up' && ! $lockedTicket->assessment_completed_at) {
                    return 'A nurse must complete the patient assessment before consultation can be completed.';
                }
                $patient = $lockedTicket->patient;
                $incident = $patient?->biteIncidents->sortBy('date_of_bite')->first();
                $doseCount = (int) $patient?->planned_dose_count;
                if (! $incident?->exposure_category || ! $patient?->vaccine_type || ! in_array($doseCount, [3, 4], true)
                    || $patient->vaccinationSchedules->count() !== $doseCount) {
                    return 'Complete the exposure assessment and provider-ordered vaccination plan before completing this consultation.';
                }
            }
            if ($lockedTicket->vaccination_schedule_id && $lockedTicket->vaccinationSchedule?->status !== 'administered') {
                return 'Administer the linked dose before completing this follow-up ticket.';
            }

            $lockedTicket->update(['status' => 'completed', 'completed_at' => now()]);
            Audit::log('queue.complete', "Completed ticket {$lockedTicket->ticket_number}", $lockedTicket);
            return null;
        });

        if ($message) return back()->withErrors(['ticket' => $message]);

        return back()->with('status', "Ticket {$ticket->ticket_number} completed.");
    }
}
