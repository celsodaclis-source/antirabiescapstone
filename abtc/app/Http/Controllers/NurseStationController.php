<?php

namespace App\Http\Controllers;

use App\Models\NurseStation;
use App\Models\Ticket;
use App\Services\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NurseStationController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $nurse = request()->user();
        $station = NurseStation::where('user_id', $nurse->id)->first();
        if (! $station) {
            return redirect()->route('dashboard')->withErrors(['station' => 'Open an available nurse station before reviewing patients.']);
        }

        $currentTicket = Ticket::with(['patient.biteIncidents'])
            ->where('assigned_staff_id', $nurse->id)
            ->where('counter_assigned', $station->id)
            ->whereIn('status', ['called', 'in_progress'])
            ->whereIn('service_stage', ['assessment', 'medical_review'])
            ->first();

        $waiting = Ticket::with(['patient.biteIncidents', 'assessmentCompletedBy'])
            ->where('status', 'waiting')
            ->whereIn('service_stage', ['assessment', 'medical_review'])
            ->whereNull('assessment_completed_at')
            ->priorityFirst()
            ->paginate(15);

        return view('nurse.station', compact('station', 'waiting', 'currentTicket'));
    }

    public function callNext(): RedirectResponse
    {
        $nurse = request()->user();
        [$ticket, $message] = DB::transaction(function () use ($nurse) {
            NurseStation::orderBy('id')->lockForUpdate()->get();
            $station = NurseStation::where('user_id', $nurse->id)->lockForUpdate()->first();
            if (! $station) return [null, 'You must be assigned to a nurse station before calling a patient.'];

            $active = Ticket::where('assigned_staff_id', $nurse->id)
                ->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->first();
            if ($active) return [null, "Complete the assessment for {$active->ticket_number} before calling another patient."];

            $stationActive = Ticket::where('counter_assigned', $station->id)
                ->whereHas('assignedStaff', fn ($query) => $query->where('role', 'Nurse'))
                ->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->first();
            if ($stationActive) return [null, "{$station->name} is already serving {$stationActive->ticket_number}."];

            $next = Ticket::where('status', 'waiting')
                ->whereIn('service_stage', ['assessment', 'medical_review'])
                ->whereNull('assessment_completed_at')
                ->priorityFirst()->lockForUpdate()->first();
            if (! $next) return [null, 'No patients are waiting for nurse assessment.'];

            $next->update([
                'status' => 'in_progress',
                'assigned_staff_id' => $nurse->id,
                'counter_assigned' => $station->id,
                'called_at' => now(),
                'served_at' => now(),
            ]);

            return [$next, null];
        });

        if (! $ticket) return back()->withErrors(['station' => $message]);

        Audit::log('nurse.station.call_next', "Nurse called ticket {$ticket->ticket_number} to {$ticket->counter_assigned} station", $ticket);

        return back()->with('status', "Now serving {$ticket->ticket_number}.");
    }

    public function completeAssessment(Ticket $ticket): RedirectResponse
    {
        $nurse = request()->user();

        try {
            DB::transaction(function () use ($ticket, $nurse) {
                $station = NurseStation::where('user_id', $nurse->id)->lockForUpdate()->first();
                if (! $station) {
                    throw ValidationException::withMessages(['station' => 'You must be assigned to a nurse station to assess patients.']);
                }

                $lockedTicket = Ticket::with('patient.biteIncidents')->lockForUpdate()->findOrFail($ticket->id);
                if ($lockedTicket->status !== 'in_progress' || $lockedTicket->assigned_staff_id !== $nurse->id
                    || $lockedTicket->counter_assigned !== $station->id
                    || ! in_array($lockedTicket->service_stage, ['assessment', 'medical_review'], true)) {
                    throw ValidationException::withMessages(['assessment' => 'This ticket is not being served at your active nurse station.']);
                }
                if ($lockedTicket->assessment_completed_at) {
                    throw ValidationException::withMessages(['assessment' => 'This assessment has already been completed.']);
                }

                $patient = $lockedTicket->patient;
                $incident = $patient?->biteIncidents->sortBy('date_of_bite')->first();
                if (! $patient || ! $incident || ! $patient->first_name || ! $patient->last_name || ! $patient->dob || ! $patient->sex || ! $patient->address || ! $patient->barangay || ! $incident->date_of_bite || ! $incident->animal_type) {
                    throw ValidationException::withMessages(['assessment' => 'The patient registration and bite incident record must be complete before assessment can be marked complete.']);
                }

                $lockedTicket->update([
                    'status' => 'waiting',
                    'assessment_completed_by' => $nurse->id,
                    'assessment_completed_at' => now(),
                    'assigned_staff_id' => null,
                    'counter_assigned' => null,
                ]);

                Audit::log('nurse.assessment_complete', "Nurse assessment completed for ticket {$lockedTicket->ticket_number}", $lockedTicket);
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', "Nurse assessment completed for {$ticket->ticket_number}. It is now available to the Doctor queue.");
    }

    public function assign(NurseStation $station): RedirectResponse
    {
        $user = request()->user();

        try {
            DB::transaction(function () use ($station, $user) {
                $lockedStation = NurseStation::whereKey($station->id)->lockForUpdate()->firstOrFail();

                if ($user->nurseStation()->exists()) {
                    throw new StationAssignmentException('You are already assigned to a station. Release it before choosing another.');
                }
                if ($lockedStation->user_id !== null) {
                    throw new StationAssignmentException('That station has just been assigned to another nurse.');
                }

                $lockedStation->update(['user_id' => $user->id]);
            });
        } catch (StationAssignmentException $exception) {
            return back()->withErrors(['station' => $exception->getMessage()]);
        } catch (QueryException $exception) {
            // The unique user_id constraint is the final guard against simultaneous assignments.
            if (NurseStation::where('user_id', $user->id)->exists()) {
                return back()->withErrors(['station' => 'You are already assigned to a station. Release it before choosing another.']);
            }

            throw $exception;
        }

        return back()->with('status', 'You are now on duty at '.$station->name.'.');
    }

    public function release(): RedirectResponse
    {
        $user = request()->user();

        DB::transaction(function () use ($user) {
            $station = NurseStation::where('user_id', $user->id)->lockForUpdate()->first();
            if ($station && Ticket::where('assigned_staff_id', $user->id)->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['station' => 'Finish the active assessment before leaving your station.']);
            }
            $station?->update(['user_id' => null]);
        });

        return redirect()->route('dashboard')->with('status', 'You are off duty and your station is available.');
    }
}

class StationAssignmentException extends \RuntimeException {}
