<?php

namespace App\Http\Controllers;

use App\Models\DoctorStation;
use App\Models\Patient;
use App\Models\Ticket;
use App\Services\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DoctorStationController extends Controller
{
    public function data(): JsonResponse
    {
        $doctor = request()->user();
        $station = DoctorStation::where('user_id', $doctor->id)->first();
        if (! $station) return response()->json(['message' => 'No active doctor station.'], 409);

        $eligible = Ticket::with('patient')
            ->where('status', 'waiting')
            ->whereIn('service_stage', ['assessment', 'medical_review'])
            ->whereNotNull('assessment_completed_at')
            ->whereDoesntHave('patient.tickets', fn ($query) => $query->whereIn('status', ['called', 'in_progress']))
            ->priorityFirst();
        $waitingCount = (clone $eligible)->count();
        $waiting = (clone $eligible)->paginate(15);

        $currentTicket = Ticket::with(['patient.vaccinationSchedules', 'patient.biteIncidents'])
            ->where('assigned_staff_id', $doctor->id)
            ->where(function ($query) use ($station) {
                $query->where('doctor_station_id', $station->id)
                    ->orWhere(function ($legacy) use ($station) {
                        $legacy->whereNull('doctor_station_id')->where('counter_assigned', $station->id);
                    });
            })
            ->whereIn('status', ['called', 'in_progress'])
            ->orderByDesc('called_at')->first();

        $planReady = false;
        if ($currentTicket?->patient) {
            $patient = $currentTicket->patient;
            $incident = $patient->biteIncidents->sortBy('date_of_bite')->first();
            $doseCount = (int) $patient->planned_dose_count;
            $planReady = (bool) ($incident?->exposure_category && $patient->vaccine_type
                && in_array($doseCount, [3, 4], true)
                && $patient->vaccinationSchedules->count() === $doseCount);
        }

        return response()->json([
            'waiting_count' => $waitingCount,
            'waiting' => $waiting->getCollection()->map(fn (Ticket $ticket) => [
                'ticket_number' => $ticket->ticket_number,
                'status' => ucfirst(str_replace('_', ' ', $ticket->status)),
            ])->values(),
            'current_ticket_id' => $currentTicket?->id,
            'current_status' => $currentTicket?->status,
            'plan_ready' => $planReady,
            'patients_consulted_today' => Ticket::where('assigned_staff_id', $doctor->id)
                ->where('status', 'completed')->whereIn('service_stage', ['assessment', 'medical_review'])
                ->whereBetween('completed_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function show(): View|RedirectResponse
    {
        $doctor = request()->user();
        $station = DoctorStation::where('user_id', $doctor->id)->first();
        if (! $station) {
            return redirect()->route('dashboard')->withErrors(['station' => 'Open an available doctor station before managing consultations.']);
        }

        $eligible = Ticket::with(['patient.biteIncidents', 'assessmentCompletedBy'])
            ->where('status', 'waiting')
            ->whereIn('service_stage', ['assessment', 'medical_review'])
            ->whereNotNull('assessment_completed_at')
            ->whereDoesntHave('patient.tickets', fn ($query) => $query->whereIn('status', ['called', 'in_progress']))
            ->priorityFirst();
        $doctorWaiting = (clone $eligible)->count();

        $currentTicket = Ticket::with(['patient.biteIncidents', 'patient.vaccinationSchedules', 'assignedStaff', 'assessmentCompletedBy'])
            ->where('assigned_staff_id', $doctor->id)
            ->where(function ($query) use ($station) {
                $query->where('doctor_station_id', $station->id)
                    ->orWhere(function ($legacy) use ($station) {
                        $legacy->whereNull('doctor_station_id')->where('counter_assigned', $station->id);
                    });
            })
            ->whereIn('status', ['called', 'in_progress'])
            ->orderByDesc('called_at')
            ->first();

        $waiting = $eligible->paginate(15);
        $stationBusy = Ticket::where('counter_assigned', $station->id)
            ->whereIn('status', ['called', 'in_progress'])
            ->where(function ($query) use ($doctor, $station) {
                $query->whereNull('doctor_station_id')->where('assigned_staff_id', '!=', $doctor->id)
                    ->orWhere(function ($stationTicket) use ($station, $doctor) {
                        $stationTicket->where('doctor_station_id', '!=', $station->id)
                            ->orWhere('assigned_staff_id', '!=', $doctor->id);
                    });
            })->exists();
        $doctorHasActiveTicket = Ticket::where('assigned_staff_id', $doctor->id)
            ->whereIn('status', ['called', 'in_progress'])->exists();
        $callDisabled = (bool) $currentTicket || $stationBusy || $doctorHasActiveTicket;
        $patientsConsultedToday = Ticket::where('assigned_staff_id', $doctor->id)
            ->where('status', 'completed')
            ->whereIn('service_stage', ['assessment', 'medical_review'])
            ->whereBetween('completed_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();

        $patientRecordReady = false;
        $planReady = false;
        if ($currentTicket?->patient) {
            $patient = $currentTicket->patient;
            $incident = $patient->biteIncidents->sortBy('date_of_bite')->first();
            $patientRecordReady = (bool) ($patient->first_name && $patient->last_name && $patient->dob && $patient->sex
                && $patient->address && $patient->barangay && $incident?->date_of_bite && $incident?->animal_type);
            $planReady = (bool) ($incident?->exposure_category && $patient->vaccine_type && in_array((int) $patient->planned_dose_count, [3, 4], true)
                && $patient->vaccinationSchedules->count() === (int) $patient->planned_dose_count);
        }

        return view('doctor.station', compact(
            'station', 'doctorWaiting', 'patientsConsultedToday', 'currentTicket', 'waiting', 'patientRecordReady', 'planReady', 'stationBusy', 'doctorHasActiveTicket', 'callDisabled'
        ));
    }

    public function assign(DoctorStation $station): RedirectResponse
    {
        $doctor = request()->user();

        try {
            DB::transaction(function () use ($station, $doctor) {
                $lockedStation = DoctorStation::whereKey($station->id)->lockForUpdate()->firstOrFail();

                if (DoctorStation::where('user_id', $doctor->id)->exists()) {
                    throw new DoctorStationAssignmentException('You are already assigned to a station. Release it before choosing another.');
                }
                if ($lockedStation->user_id !== null) {
                    throw new DoctorStationAssignmentException('That station has just been assigned to another doctor.');
                }

                $lockedStation->update(['user_id' => $doctor->id]);
            });
        } catch (DoctorStationAssignmentException $exception) {
            return back()->withErrors(['station' => $exception->getMessage()]);
        } catch (QueryException $exception) {
            if (DoctorStation::where('user_id', $doctor->id)->exists()) {
                return back()->withErrors(['station' => 'You are already assigned to a station. Release it before choosing another.']);
            }

            if (DoctorStation::whereKey($station->id)->whereNotNull('user_id')->exists()) {
                return back()->withErrors(['station' => 'That station has just been assigned to another doctor.']);
            }

            throw $exception;
        }

        return redirect()->route('doctor.station.show')->with('status', 'You are now on duty at '.$station->name.'.');
    }

    public function callNext(): RedirectResponse
    {
        $doctor = request()->user();
        [$ticket, $message] = DB::transaction(function () use ($doctor) {
            // Serializing station calls ensures simultaneous doctors cannot claim the same waiting ticket.
            DoctorStation::orderBy('id')->lockForUpdate()->get();
            $station = DoctorStation::where('user_id', $doctor->id)->lockForUpdate()->first();
            if (! $station) {
                return [null, 'You must be assigned to a doctor station before calling a patient.'];
            }

            $active = Ticket::where('assigned_staff_id', $doctor->id)
                ->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->first();
            if ($active) {
                return [null, "Complete the current consultation for {$active->ticket_number} before calling another patient."];
            }
            $stationActive = Ticket::where('counter_assigned', $station->id)
                ->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->first();
            if ($stationActive) {
                return [null, "Station {$station->name} is already serving {$stationActive->ticket_number}."];
            }

            $next = Ticket::where('status', 'waiting')
                ->whereIn('service_stage', ['assessment', 'medical_review'])
                ->whereNotNull('assessment_completed_at')
                ->whereDoesntHave('patient.tickets', fn ($query) => $query->whereIn('status', ['called', 'in_progress']))
                ->priorityFirst()->lockForUpdate()->first();
            if (! $next) {
                return [null, 'No patients with a completed nurse assessment are waiting for consultation.'];
            }

            Patient::whereKey($next->patient_id)->lockForUpdate()->firstOrFail();
            if (Ticket::where('patient_id', $next->patient_id)->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->exists()) {
                return [null, 'This patient already has an active consultation with a doctor.'];
            }

            $next->update([
                'status' => 'in_progress',
                'doctor_station_id' => $station->id,
                'counter_assigned' => $station->id,
                'assigned_staff_id' => $doctor->id,
                'called_at' => now(),
                'served_at' => now(),
            ]);

            return [$next, null];
        });

        if (! $ticket) {
            return back()->withErrors(['consultation' => $message]);
        }

        Audit::log('doctor.station.call_next', "Doctor called ticket {$ticket->ticket_number} to {$ticket->counter_assigned} station", $ticket);

        return back()->with('status', "Now serving {$ticket->ticket_number}.");
    }

    public function complete(Ticket $ticket): RedirectResponse
    {
        $doctor = request()->user();

        try {
            DB::transaction(function () use ($ticket, $doctor) {
                $station = DoctorStation::where('user_id', $doctor->id)->lockForUpdate()->first();
                if (! $station) {
                    throw ValidationException::withMessages(['consultation' => 'You must be assigned to a doctor station to complete a consultation.']);
                }

                $lockedTicket = Ticket::with(['patient.biteIncidents', 'patient.vaccinationSchedules'])
                    ->lockForUpdate()->findOrFail($ticket->id);
                if ($lockedTicket->status === 'completed') {
                    throw ValidationException::withMessages(['consultation' => 'This consultation has already been completed.']);
                }
                $stationMatches = $lockedTicket->doctor_station_id === $station->id
                    || ($lockedTicket->doctor_station_id === null && $lockedTicket->counter_assigned === $station->id);
                if ($lockedTicket->status !== 'in_progress' || $lockedTicket->assigned_staff_id !== $doctor->id || ! $stationMatches) {
                    throw ValidationException::withMessages(['consultation' => 'This patient is not assigned to your active station.']);
                }
                if ($lockedTicket->service_stage !== 'follow_up' && ! $lockedTicket->assessment_completed_at) {
                    throw ValidationException::withMessages(['consultation' => 'A nurse must complete the patient assessment before this consultation can be completed.']);
                }
                if ($lockedTicket->vaccination_schedule_id && $lockedTicket->vaccinationSchedule?->status !== 'administered') {
                    throw ValidationException::withMessages(['consultation' => 'The linked vaccination dose must be administered before its follow-up ticket can be completed.']);
                }

                $patient = $lockedTicket->patient;
                $incident = $patient?->biteIncidents->sortBy('date_of_bite')->first();
                $doseCount = (int) $patient?->planned_dose_count;
                if (! $incident?->exposure_category || ! $patient?->vaccine_type || ! in_array($doseCount, [3, 4], true)
                    || $patient->vaccinationSchedules->count() !== $doseCount) {
                    throw ValidationException::withMessages([
                        'consultation' => 'Complete the patient Exposure Category, Vaccine Type, provider-ordered Schedule, and dose count before completing this consultation.',
                    ]);
                }

                $lockedTicket->update(['status' => 'completed', 'completed_at' => now()]);
                Audit::log('doctor.consultation.complete', "Doctor completed consultation for ticket {$lockedTicket->ticket_number}", $lockedTicket);
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', "Consultation for {$ticket->ticket_number} completed. Vaccination doses remain scheduled until administered.");
    }

    public function release(): RedirectResponse
    {
        $doctor = request()->user();
        $message = DB::transaction(function () use ($doctor) {
            $station = DoctorStation::where('user_id', $doctor->id)->lockForUpdate()->first();
            if (! $station) {
                return 'You are not currently assigned to a doctor station.';
            }
            if (Ticket::where('assigned_staff_id', $doctor->id)->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->exists()) {
                return 'Complete or resolve the active consultation before leaving your station.';
            }
            $station->update(['user_id' => null]);
            return null;
        });

        if ($message) {
            return back()->withErrors(['station' => $message]);
        }

        return redirect()->route('dashboard')->with('status', 'You are off duty and your station is available.');
    }
}

class DoctorStationAssignmentException extends \RuntimeException {}
