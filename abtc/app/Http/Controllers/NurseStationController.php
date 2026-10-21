<?php

namespace App\Http\Controllers;

use App\Models\NurseStation;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use App\Services\Audit;
use App\Services\VaccineAuthorization;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NurseStationController extends Controller
{
    public function data(): JsonResponse
    {
        $nurse = request()->user();
        $station = NurseStation::where('user_id', $nurse->id)->first();
        if (! $station) return response()->json(['message' => 'No active nurse station.'], 409);

        $currentTicket = Ticket::with(['patient.biteIncidents', 'nurseAssessment'])
            ->where('assigned_staff_id', $nurse->id)
            ->where('counter_assigned', $station->id)
            ->whereIn('status', ['called', 'in_progress'])
            ->whereIn('service_stage', ['assessment', 'medical_review', 'follow_up'])
            ->with('vaccinationSchedule')
            ->first();
        $vaccineReason = $currentTicket?->service_stage === 'follow_up'
            ? $this->vaccineActionBlockReason($currentTicket) : null;

        $waitingQuery = $this->constrainNurseQueue(Ticket::with('patient'))
            ->whereDoesntHave('patient.tickets', fn ($query) => $query->whereIn('status', ['called', 'in_progress']))
            ->priorityFirst();
        $waitingCount = (clone $waitingQuery)->count();

        return response()->json([
            'current' => $currentTicket ? [
                'id' => $currentTicket->id,
                'number' => $currentTicket->ticket_number,
                'patient' => $currentTicket->patient?->fullName() ?? 'Patient record unavailable',
                'patient_url' => $currentTicket->patient ? route('patients.show', $currentTicket->patient) : null,
                'patient_id' => $currentTicket->patient?->id,
                'visit' => ucfirst($currentTicket->visit_type),
                'assessment_form_url' => $currentTicket->service_stage === 'follow_up' ? null : route('nurse.station.assessment.form', $currentTicket),
                'assessment_status' => $currentTicket->nurseAssessment?->statusLabel() ?? 'Not started',
                'follow_up' => $currentTicket->service_stage === 'follow_up',
                'vaccine_url' => $currentTicket->service_stage === 'follow_up' ? route('nurse.station.vaccine.form', $currentTicket) : null,
                'vaccine_available' => $currentTicket->service_stage === 'follow_up' && $vaccineReason === null,
                'vaccine_reason' => $vaccineReason,
                'incident' => $currentTicket->patient?->biteIncidents->sortBy('date_of_bite')->first()?->only([
                    'date_of_bite', 'animal_type', 'exposure_category', 'bite_site', 'animal_status', 'animal_vaccinated',
                ]),
            ] : null,
            'waiting' => $waitingQuery->take(15)->get()->map(fn (Ticket $ticket) => [
                'number' => $ticket->ticket_number,
                'patient' => $ticket->patient?->fullName() ?? 'Patient record unavailable',
                'patient_url' => $ticket->patient ? route('patients.show', $ticket->patient) : null,
                'stage' => ucfirst(str_replace('_', ' ', $ticket->service_stage)),
                'visit' => ucfirst($ticket->visit_type),
            ])->values(),
            'waiting_count' => $waitingCount,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function show(): View|RedirectResponse
    {
        $nurse = request()->user();
        $station = NurseStation::where('user_id', $nurse->id)->first();
        if (! $station) {
            return redirect()->route('dashboard')->withErrors(['station' => 'Open an available nurse station before reviewing patients.']);
        }

        $currentTicket = Ticket::with(['patient.biteIncidents', 'nurseAssessment'])
            ->where('assigned_staff_id', $nurse->id)
            ->where('counter_assigned', $station->id)
            ->whereIn('status', ['called', 'in_progress'])
            ->whereIn('service_stage', ['assessment', 'medical_review', 'follow_up'])
            ->with(['vaccinationSchedule', 'patient.vaccinationSchedules'])
            ->first();
        $vaccineReason = $currentTicket?->service_stage === 'follow_up'
            ? $this->vaccineActionBlockReason($currentTicket) : null;

        $waiting = $this->constrainNurseQueue(Ticket::with(['patient.biteIncidents', 'assessmentCompletedBy']))
            ->whereDoesntHave('patient.tickets', fn ($query) => $query->whereIn('status', ['called', 'in_progress']))
            ->priorityFirst()
            ->paginate(15);

        $heldForResume = Ticket::with(['patient', 'nurseAssessment.nurse'])
            ->where('status', 'held')
            ->whereIn('service_stage', ['assessment', 'medical_review'])
            ->whereHas('nurseAssessment', fn ($query) => $query->where('status', 'on_hold'))
            ->priorityFirst()
            ->limit(10)
            ->get();

        return view('nurse.station', compact('station', 'waiting', 'currentTicket', 'vaccineReason', 'heldForResume'));
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

            $next = $this->constrainNurseQueue(Ticket::query())
                ->whereDoesntHave('patient.tickets', fn ($query) => $query->whereIn('status', ['called', 'in_progress']))
                ->priorityFirst()->lockForUpdate()->first();
            if (! $next) return [null, 'No eligible nurse assessment or due follow-up dose is waiting.'];

            Patient::whereKey($next->patient_id)->lockForUpdate()->firstOrFail();
            if (Ticket::where('patient_id', $next->patient_id)->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->exists()) {
                return [null, 'This patient is already being served at another station.'];
            }

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

    public function vaccineForm(Ticket $ticket): View|RedirectResponse
    {
        $nurse = request()->user();
        $station = NurseStation::where('user_id', $nurse->id)->first();
        abort_unless($station, 403, 'Open a nurse station before administering a vaccine.');

        $ticket->load(['patient.biteIncidents', 'patient.vaccinationSchedules', 'vaccinationSchedule']);
        abort_unless($ticket->status === 'in_progress'
            && $ticket->assigned_staff_id === $nurse->id
            && $ticket->counter_assigned === $station->id
            && $ticket->service_stage === 'follow_up', 403, 'This follow-up ticket is not assigned to your active station.');

        $schedule = $ticket->vaccinationSchedule;
        $patient = $ticket->patient;
        $authorization = $this->vaccineAuthorizationError($patient, $schedule);
        $sites = config('vaccination.administration_sites', []);
        $batches = collect();
        if (! $authorization && $patient->vaccine_type && ! $this->isImmunoglobulin($patient->vaccine_type)) {
            $batches = VaccineInventory::where('vaccine_name', $patient->vaccine_type)
                ->where('quantity', '>', 0)
                ->whereDate('expiration_date', '>=', today())
                ->orderBy('expiration_date')->orderBy('id')->get();
        }

        return view('nurse.vaccine-administration', compact('ticket', 'station', 'schedule', 'patient', 'authorization', 'sites', 'batches'));
    }

    public function administerVaccine(Request $request, Ticket $ticket): RedirectResponse
    {
        $nurse = $request->user();
        $sites = config('vaccination.administration_sites', []);
        if (! $sites) {
            return back()->withErrors(['administration_site' => 'The clinic must configure its approved vaccine administration sites before a dose can be recorded.']);
        }
        $data = $request->validate([
            'inventory_id' => ['required', 'integer', 'exists:vaccine_inventory,id'],
            'administration_date' => ['required', 'date', 'before_or_equal:today'],
            'administration_site' => ['required', 'string', \Illuminate\Validation\Rule::in($sites)],
            'administration_remarks' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $message = DB::transaction(function () use ($ticket, $nurse, $data) {
                $station = NurseStation::where('user_id', $nurse->id)->lockForUpdate()->first();
                if (! $station) throw ValidationException::withMessages(['station' => 'You must be assigned to a nurse station.']);

                $lockedTicket = Ticket::with('vaccinationSchedule')->lockForUpdate()->findOrFail($ticket->id);
                if ($lockedTicket->status !== 'in_progress' || $lockedTicket->assigned_staff_id !== $nurse->id
                    || $lockedTicket->counter_assigned !== $station->id || $lockedTicket->service_stage !== 'follow_up') {
                    throw ValidationException::withMessages(['ticket' => 'This follow-up ticket is no longer assigned to your active nurse station.']);
                }

                $schedule = VaccinationSchedule::lockForUpdate()->findOrFail($lockedTicket->vaccination_schedule_id);
                $patient = Patient::with(['biteIncidents', 'vaccinationSchedules'])->lockForUpdate()->findOrFail($schedule->patient_id);
                $reason = $schedule->patient_id !== $patient->id ? 'The dose does not belong to this patient.' : $this->vaccineAuthorizationError($patient, $schedule);
                if ($reason) {
                    throw ValidationException::withMessages(['authorization' => $reason]);
                }

                $administrationDate = \Illuminate\Support\Carbon::parse($data['administration_date'], config('app.timezone'))->startOfDay();
                if ($schedule->scheduled_date->gt($administrationDate)) {
                    throw ValidationException::withMessages(['administration_date' => 'The scheduled dose is not due on the selected administration date.']);
                }
                if (VaccinationSchedule::where('patient_id', $patient->id)->where('dose_number', '<', $schedule->dose_number)
                    ->where('status', '!=', 'administered')->exists()) {
                    throw ValidationException::withMessages(['dose' => 'Earlier doses must be administered first.']);
                }

                $batch = VaccineInventory::whereKey($data['inventory_id'])->lockForUpdate()->first();
                if (! $batch || $batch->vaccine_name !== $patient->vaccine_type || $this->isImmunoglobulin($batch->vaccine_name)
                    || $batch->quantity < 1 || $batch->expiration_date->lt($administrationDate)) {
                    throw ValidationException::withMessages(['inventory_id' => 'Choose an in-stock, unexpired batch matching the doctor-ordered vaccine.']);
                }

                $batch->decrement('quantity');
                $schedule->update([
                    'status' => 'administered', 'administered_at' => $administrationDate,
                    'administered_by' => $nurse->id, 'inventory_id' => $batch->id,
                    'administration_site' => $data['administration_site'],
                    'administration_remarks' => $data['administration_remarks'],
                ]);
                $lockedTicket->update(['status' => 'completed', 'completed_at' => now(), 'assigned_staff_id' => null, 'counter_assigned' => null]);
                Audit::log('nurse.vaccine_administer', "Nurse administered dose {$schedule->dose_number} to ticket {$lockedTicket->ticket_number} using batch {$batch->batch_number}; site {$data['administration_site']}", $schedule);

                return "Dose {$schedule->dose_number} recorded for {$lockedTicket->ticket_number}. Batch {$batch->batch_number} has {$batch->fresh()->quantity} remaining.";
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput($request->except('_token'));
        }

        return redirect()->route('nurse.station.show')->with('status', $message);
    }

    private function vaccineAuthorizationError(?Patient $patient, ?VaccinationSchedule $schedule): ?string
    {
        return VaccineAuthorization::planError($patient, $schedule);
    }

    private function vaccineActionBlockReason(Ticket $ticket): ?string
    {
        return VaccineAuthorization::administrationBlockReason($ticket);
    }

    private function constrainNurseQueue($query)
    {
        $followUpTypes = $this->eligibleFollowUpVaccineTypes();

        return $query->where('status', 'waiting')->where(function ($query) use ($followUpTypes) {
            $query->where(function ($assessment) {
                $assessment->whereIn('service_stage', ['assessment', 'medical_review'])->whereNull('assessment_completed_at');
            })->orWhere(function ($followUp) use ($followUpTypes) {
                $followUp->where('service_stage', 'follow_up')
                    ->whereHas('vaccinationSchedule', fn ($schedule) => $schedule
                        ->whereIn('status', ['scheduled', 'missed'])->whereDate('scheduled_date', '<=', today())
                        ->whereRaw("not exists (select 1 from vaccination_schedules earlier_dose where earlier_dose.patient_id = vaccination_schedules.patient_id and earlier_dose.dose_number < vaccination_schedules.dose_number and earlier_dose.status != 'administered')"))
                    ->whereHas('patient', fn ($patient) => $patient
                        ->whereIn('vaccine_type', $followUpTypes)
                        ->whereIn('planned_dose_count', [3, 4])
                        ->whereHas('biteIncidents', fn ($incident) => $incident->whereNotNull('exposure_category'))
                        ->whereRaw('(select count(*) from vaccination_schedules as planned_doses where planned_doses.patient_id = patients.id) = patients.planned_dose_count'));
            });
        });
    }

    private function eligibleFollowUpVaccineTypes(): array
    {
        if (! config('vaccination.administration_sites', [])) return [];
        return VaccineInventory::where('quantity', '>', 0)->whereDate('expiration_date', '>=', today())
            ->pluck('vaccine_name')->unique()->reject(fn (string $name) => $this->isImmunoglobulin($name))->values()->all();
    }

    private function isImmunoglobulin(string $productName): bool
    {
        return VaccineAuthorization::isImmunoglobulin($productName);
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
