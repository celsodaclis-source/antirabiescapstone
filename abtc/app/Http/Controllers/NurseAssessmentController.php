<?php

namespace App\Http\Controllers;

use App\Models\NurseAssessment;
use App\Models\NurseStation;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccineInventory;
use App\Services\Audit;
use App\Services\VaccineAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Nurse Assessment form: vital signs, nursing assessment notes, and the
 * mutually exclusive patient routing decision (doctor, vaccine, on hold).
 */
class NurseAssessmentController extends Controller
{
    private const NURSE_STAGES = ['assessment', 'medical_review', 'follow_up'];

    public function form(Ticket $ticket): View
    {
        [$station, $patient, $incident, $assessment] = $this->assessmentContext($ticket);
        $vaccinePlan = $this->vaccineRoutePlan($ticket);

        return view('nurse.assessment', [
            'ticket' => $ticket,
            'station' => $station,
            'patient' => $patient,
            'incident' => $incident,
            'assessment' => $assessment,
            'vaccinePlan' => $vaccinePlan,
            'administrationSites' => config('vaccination.administration_sites', []),
            'defaultRoutingOption' => old('routing_option', $assessment?->routing_option
                ?? ($ticket->service_stage === 'follow_up' ? 'vaccine_administration' : 'send_to_doctor')),
        ]);
    }

    public function saveDraft(Request $request, Ticket $ticket): RedirectResponse
    {
        [$station] = $this->assessmentContext($ticket);
        $validated = Validator::make($request->all(), $this->draftRules())->validate();
        $this->persistDraft($ticket, $station, $request->user()->id, $validated);

        return redirect()->route('nurse.station.assessment.form', $ticket)
            ->with('status', 'Assessment draft saved. The patient remains in your queue and no routing was applied.');
    }

    public function review(Request $request, Ticket $ticket): RedirectResponse
    {
        [$station] = $this->assessmentContext($ticket);
        $draft = Validator::make($request->all(), $this->draftRules())->validate();
        $this->persistDraft($ticket, $station, $request->user()->id, $draft);

        return redirect()->route('nurse.station.assessment.review-page', $ticket);
    }

    public function reviewPage(Ticket $ticket): View
    {
        [$station, $patient, $incident, $assessment] = $this->assessmentContext($ticket);
        abort_unless($assessment, 404, 'Save the assessment before reviewing its workflow rules.');

        return view('nurse.assessment-review', [
            'ticket' => $ticket,
            'station' => $station,
            'patient' => $patient,
            'incident' => $incident,
            'assessment' => $assessment,
            'missingErrors' => $this->blockingErrors($assessment, $ticket),
            'prerequisites' => $this->routingPrerequisites($ticket, $assessment),
        ]);
    }

    public function finalize(Request $request, Ticket $ticket): RedirectResponse
    {
        $nurse = $request->user();
        $request->validate([
            'confirmed' => ['accepted'],
            'routing_option' => ['required', Rule::in(NurseAssessment::ROUTING_OPTIONS)],
        ]);

        try {
            return DB::transaction(function () use ($ticket, $nurse, $request) {
                $station = NurseStation::where('user_id', $nurse->id)->lockForUpdate()->first();
                if (! $station) {
                    throw ValidationException::withMessages(['station' => 'You must be assigned to a nurse station.']);
                }

                $lockedTicket = Ticket::with(['patient.biteIncidents', 'patient.vaccinationSchedules', 'vaccinationSchedule'])
                    ->lockForUpdate()->findOrFail($ticket->id);
                $this->assertActiveTicket($lockedTicket, $nurse->id, $station->id);

                $assessment = NurseAssessment::where('ticket_id', $lockedTicket->id)->lockForUpdate()->first();
                if (! $assessment || $assessment->nurse_id !== $nurse->id) {
                    throw ValidationException::withMessages(['assessment' => 'Save an assessment draft for this assigned patient before routing.']);
                }
                if ($assessment->isCompleted()) {
                    throw ValidationException::withMessages(['assessment' => 'This assessment has already been completed.']);
                }
                $assessment->routing_option = $request->string('routing_option')->toString();

                return match ($assessment->routing_option) {
                    'send_to_doctor' => $this->routeToDoctor($lockedTicket, $assessment, $nurse),
                    'vaccine_administration' => $this->routeToVaccine($lockedTicket, $assessment, $nurse),
                    'on_hold' => $this->placeOnHold($lockedTicket, $assessment, $nurse),
                };
            });
        } catch (ValidationException $exception) {
            return redirect()->route('nurse.station.assessment.review-page', $ticket)
                ->withErrors($exception->errors());
        }
    }

    /**
     * Legacy single-request completion used by the queue workflow tests and
     * integrations: requires the bite incident fields and sends the patient
     * to the doctor queue, persisting any optional vitals submitted with it.
     */
    public function sendToDoctor(Request $request, Ticket $ticket): RedirectResponse
    {
        $nurse = $request->user();

        try {
            DB::transaction(function () use ($ticket, $nurse, $request) {
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

                $rules = $this->draftRules();
                $rules['bite_site'] = ['required', 'string', 'max:150'];
                $rules['animal_status'] = ['required', Rule::in(['Healthy', 'Sick', 'Dead', 'Unknown'])];
                $rules['animal_vaccinated'] = ['required', 'boolean'];
                $data = Validator::make($request->all(), $rules)->validate();

                $patient = $lockedTicket->patient;
                $incident = $patient?->biteIncidents->sortBy('date_of_bite')->first();
                if (! $patient || ! $incident || ! $patient->first_name || ! $patient->last_name || ! $patient->dob || ! $patient->sex || ! $patient->address || ! $patient->barangay || ! $incident->date_of_bite || ! $incident->animal_type) {
                    throw ValidationException::withMessages(['assessment' => 'Complete the patient registration, bite incident, and required nursing assessment fields before transferring this patient.']);
                }

                $assessment = NurseAssessment::firstOrNew(['ticket_id' => $lockedTicket->id]);
                if ($assessment->exists && $assessment->isCompleted()) {
                    throw ValidationException::withMessages(['assessment' => 'This assessment has already been completed.']);
                }
                $assessment->fill(array_merge($data, [
                    'patient_id' => $lockedTicket->patient_id,
                    'nurse_id' => $nurse->id,
                    'nurse_station_id' => $station->id,
                    'status' => 'completed',
                    'routing_option' => 'send_to_doctor',
                    'draft_saved_at' => $assessment->draft_saved_at ?? now(),
                    'completed_at' => now(),
                ]));
                $assessment->save();

                $incident->update([
                    'bite_site' => $data['bite_site'],
                    'animal_status' => $data['animal_status'],
                    'animal_vaccinated' => (bool) $data['animal_vaccinated'],
                ]);

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
            return back()->withErrors($exception->errors())
                ->withInput($request->only(['bite_site', 'animal_status', 'animal_vaccinated']));
        }

        return back()->with('status', "Nurse assessment completed for {$ticket->ticket_number}. It is now available to the Doctor queue.");
    }

    /** Re-open a ticket this nurse placed on hold during assessment. */
    public function resume(Ticket $ticket): RedirectResponse
    {
        $nurse = request()->user();

        [$resumed, $message] = DB::transaction(function () use ($ticket, $nurse) {
            $station = NurseStation::where('user_id', $nurse->id)->lockForUpdate()->first();
            if (! $station) return [null, 'You must be assigned to a nurse station before resuming a patient.'];

            $active = Ticket::where('assigned_staff_id', $nurse->id)
                ->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->first();
            if ($active) return [null, "Complete the assessment for {$active->ticket_number} before resuming another patient."];

            $stationActive = Ticket::where('counter_assigned', $station->id)
                ->whereHas('assignedStaff', fn ($query) => $query->where('role', 'Nurse'))
                ->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->first();
            if ($stationActive) return [null, "{$station->name} is already serving {$stationActive->ticket_number}."];

            $locked = Ticket::with('nurseAssessment')->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status !== 'held' || ! in_array($locked->service_stage, ['assessment', 'medical_review'], true)) {
                return [null, 'Only an on-hold nurse assessment ticket can be resumed from this station.'];
            }
            if (! $locked->nurseAssessment || $locked->nurseAssessment->status !== 'on_hold') {
                return [null, 'This ticket was not placed on hold by a nurse assessment.'];
            }

            Patient::whereKey($locked->patient_id)->lockForUpdate()->firstOrFail();
            if (Ticket::where('patient_id', $locked->patient_id)->whereIn('status', ['called', 'in_progress'])->lockForUpdate()->exists()) {
                return [null, 'This patient is already being served at another station.'];
            }

            $locked->update([
                'status' => 'in_progress',
                'assigned_staff_id' => $nurse->id,
                'counter_assigned' => $station->id,
                'called_at' => now(),
                'served_at' => now(),
            ]);

            return [$locked, null];
        });

        if (! $resumed) return back()->withErrors(['station' => $message]);

        Audit::log('nurse.assessment_resume', "Nurse resumed the on-hold assessment for ticket {$resumed->ticket_number}", $resumed);

        return redirect()->route('nurse.station.assessment.form', $resumed)
            ->with('status', "Resumed the on-hold assessment for {$resumed->ticket_number}. The saved draft was restored.");
    }

    private function routeToDoctor(Ticket $ticket, NurseAssessment $assessment, $nurse): RedirectResponse
    {
        if (! in_array($ticket->service_stage, ['assessment', 'medical_review'], true)) {
            throw ValidationException::withMessages(['routing_option' => 'Follow-up dose tickets cannot be transferred to the doctor queue from this form.']);
        }
        $patient = $ticket->patient;
        if ($errors = $this->completionErrors($assessment, $patient)) {
            throw ValidationException::withMessages($errors);
        }

        $patient->biteIncidents->sortBy('date_of_bite')->first()->update([
            'bite_site' => $assessment->bite_site,
            'animal_status' => $assessment->animal_status,
            'animal_vaccinated' => (bool) $assessment->animal_vaccinated,
        ]);
        $assessment->update([
            'status' => 'completed',
            'routing_option' => 'send_to_doctor',
            'draft_saved_at' => $assessment->draft_saved_at ?? now(),
            'completed_at' => now(),
        ]);
        $ticket->update([
            'status' => 'waiting',
            'assessment_completed_by' => $nurse->id,
            'assessment_completed_at' => now(),
            'assigned_staff_id' => null,
            'counter_assigned' => null,
        ]);

        Audit::log('nurse.assessment_complete', "Nurse assessment completed for ticket {$ticket->ticket_number}; routed to the doctor queue", $ticket);

        return redirect()->route('nurse.station.show')
            ->with('status', "Assessment completed for {$ticket->ticket_number}. The patient was sent to the Doctor Waiting List.");
    }

    private function routeToVaccine(Ticket $ticket, NurseAssessment $assessment, $nurse): RedirectResponse
    {
        $patient = $ticket->patient;
        if ($errors = $this->completionErrors($assessment, $patient)) {
            throw ValidationException::withMessages($errors);
        }
        [$due, $planErrors] = $this->vaccineRoutePlan($ticket);
        if ($planErrors !== []) {
            throw ValidationException::withMessages($planErrors);
        }

        $patient->biteIncidents->sortBy('date_of_bite')->first()->update([
            'bite_site' => $assessment->bite_site,
            'animal_status' => $assessment->animal_status,
            'animal_vaccinated' => (bool) $assessment->animal_vaccinated,
        ]);
        $assessment->update([
            'status' => 'completed',
            'routing_option' => 'vaccine_administration',
            'draft_saved_at' => $assessment->draft_saved_at ?? now(),
            'completed_at' => now(),
        ]);
        // Keep the original ticket in service at this station; stock is only
        // deducted when the vaccine administration itself is saved.
        $ticket->update([
            'status' => 'in_progress',
            'service_stage' => 'follow_up',
            'vaccination_schedule_id' => $due->id,
            'assessment_completed_by' => $nurse->id,
            'assessment_completed_at' => now(),
        ]);

        Audit::log('nurse.assessment_complete', "Nurse assessment completed for ticket {$ticket->ticket_number}; routed to vaccine administration for dose {$due->dose_number}", $ticket);

        return redirect()->route('nurse.station.vaccine.form', $ticket)->with('status',
            "Assessment completed for {$ticket->ticket_number}. Review and record dose {$due->dose_number} on the vaccine administration form; no stock has been deducted yet.");
    }

    private function placeOnHold(Ticket $ticket, NurseAssessment $assessment, $nurse): RedirectResponse
    {
        if (! filled($assessment->nursing_remarks)) {
            throw ValidationException::withMessages(['nursing_remarks' => 'Record the reason for the hold and the required next step in Additional Nursing Remarks.']);
        }

        $assessment->update([
            'status' => 'on_hold',
            'routing_option' => 'on_hold',
            'draft_saved_at' => now(),
            'completed_at' => null,
        ]);
        $ticket->update([
            'status' => 'held',
            'assigned_staff_id' => null,
            'counter_assigned' => null,
            'doctor_station_id' => null,
        ]);

        Audit::log('nurse.assessment_hold', "Ticket {$ticket->ticket_number} placed on hold during nurse assessment", $ticket);

        return redirect()->route('nurse.station.show')->with('status',
            "{$ticket->ticket_number} is on hold awaiting further assessment. The original ticket and patient record are preserved.");
    }

    private function assessmentContext(Ticket $ticket): array
    {
        $nurse = request()->user();
        $station = NurseStation::where('user_id', $nurse->id)->first();
        abort_unless($station, 403, 'Open your assigned nurse station first.');

        $ticket->load(['patient.biteIncidents', 'patient.vaccinationSchedules', 'nurseAssessment', 'vaccinationSchedule']);
        $this->assertActiveTicket($ticket, $nurse->id, $station->id);
        if ($ticket->nurseAssessment && ! $ticket->nurseAssessment->isCompleted() && $ticket->nurseAssessment->nurse_id !== $nurse->id) {
            abort(403, 'This assessment was started by another nurse.');
        }

        $patient = $ticket->patient;
        $incident = $patient?->biteIncidents->sortBy('date_of_bite')->first();
        abort_unless($patient && $incident, 422, 'The patient bite incident record is missing.');

        return [$station, $patient, $incident, $ticket->nurseAssessment];
    }

    private function assertActiveTicket(Ticket $ticket, int $nurseId, int $stationId): void
    {
        if ($ticket->status !== 'in_progress' || $ticket->assigned_staff_id !== $nurseId
            || $ticket->counter_assigned !== $stationId
            || ! in_array($ticket->service_stage, self::NURSE_STAGES, true)) {
            abort(403, 'This patient is not assigned to your active nurse station.');
        }
        abort_if($ticket->assessment_completed_at, 422, 'This assessment has already been completed.');
    }

    private function draftRules(): array
    {
        return [
            'bite_site' => ['nullable', 'string', 'max:150'],
            'animal_status' => ['nullable', Rule::in(['Healthy', 'Sick', 'Dead', 'Unknown'])],
            'animal_vaccinated' => ['nullable', 'boolean'],
            'blood_pressure' => ['nullable', 'string', 'max:20', function ($attribute, $value, $fail) {
                if (! preg_match('/^\s*\d{2,3}\s*\/\s*\d{2,3}\s*(?:mmHg)?\s*$/i', (string) $value)) {
                    $fail('Blood pressure must use the systolic/diastolic format, for example 120/80.');

                    return;
                }
                preg_match('/(\d{2,3})\s*\/\s*(\d{2,3})/', (string) $value, $parts);
                $systolic = (int) $parts[1];
                $diastolic = (int) $parts[2];
                if ($systolic < 40 || $systolic > 300) $fail('Systolic pressure must be between 40 and 300 mmHg.');
                if ($diastolic < 20 || $diastolic > 200) $fail('Diastolic pressure must be between 20 and 200 mmHg.');
                if ($systolic <= $diastolic) $fail('Systolic pressure must be greater than diastolic pressure.');
            }],
            'temperature' => ['nullable', 'numeric', 'between:30,45'],
            'pulse_rate' => ['nullable', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'weight' => ['nullable', 'numeric', 'between:0.5,300'],
            'assessment_observations' => ['nullable', 'string', 'max:10000'],
            'nursing_remarks' => ['nullable', 'string', 'max:4000'],
            'routing_option' => ['nullable', Rule::in(NurseAssessment::ROUTING_OPTIONS)],
        ];
    }

    private function persistDraft(Ticket $ticket, NurseStation $station, int $nurseId, array $data): NurseAssessment
    {
        return DB::transaction(function () use ($ticket, $station, $nurseId, $data) {
            $locked = Ticket::lockForUpdate()->findOrFail($ticket->id);
            $this->assertActiveTicket($locked, $nurseId, $station->id);

            $assessment = NurseAssessment::firstOrNew(['ticket_id' => $locked->id]);
            if ($assessment->exists && $assessment->isCompleted()) {
                throw ValidationException::withMessages(['assessment' => 'This assessment has already been completed and cannot be changed here.']);
            }
            if ($assessment->exists && $assessment->nurse_id !== $nurseId) {
                throw ValidationException::withMessages(['assessment' => 'This assessment was started by another nurse.']);
            }

            $assessment->fill(array_merge($data, [
                'patient_id' => $locked->patient_id,
                'nurse_id' => $nurseId,
                'nurse_station_id' => $station->id,
                'status' => $assessment->status === 'on_hold' ? 'on_hold' : 'draft',
                'draft_saved_at' => now(),
            ]));
            $assessment->save();

            Audit::log('nurse.assessment_draft', "Saved nurse assessment draft for ticket {$locked->ticket_number}", $assessment);

            return $assessment;
        });
    }

    /** Fields required before the assessment may be marked completed. */
    private function completionErrors(NurseAssessment $assessment, ?Patient $patient = null): array
    {
        $errors = [];
        if (! filled($assessment->bite_site)) $errors['bite_site'] = 'Record the bite site before completing the assessment.';
        if (! in_array($assessment->animal_status, ['Healthy', 'Sick', 'Dead', 'Unknown'], true)) $errors['animal_status'] = 'Select the animal status before completing the assessment.';
        if (is_null($assessment->animal_vaccinated)) $errors['animal_vaccinated'] = 'Answer whether the animal was vaccinated before completing the assessment.';
        if (! filled($assessment->assessment_observations)) $errors['assessment_observations'] = 'Document the assessment and observations so the doctor can review them.';
        if ($patient && ($patient->trashed() || ! $patient->first_name || ! $patient->last_name || ! $patient->dob || ! $patient->sex || ! $patient->address || ! $patient->barangay
            || ! $patient->biteIncidents->sortBy('date_of_bite')->first()?->date_of_bite
            || ! $patient->biteIncidents->sortBy('date_of_bite')->first()?->animal_type)) {
            $errors['assessment'] = 'Complete the patient registration and bite incident before transferring this patient.';
        }

        return $errors;
    }

    /**
     * Due dose plus any blocking problem for the vaccine-administration
     * routing option. Never deducts or reserves inventory.
     */
    private function vaccineRoutePlan(Ticket $ticket): array
    {
        $patient = $ticket->patient;
        if (! $patient) return [null, ['routing_option' => 'The patient record is unavailable.']];

        if (! filled($patient->vaccine_type) || VaccineAuthorization::isImmunoglobulin($patient->vaccine_type)) {
            return [null, ['routing_option' => 'A valid doctor-ordered vaccine plan is required. Complete the doctor consultation or vaccination plan before administration.']];
        }

        $due = $ticket->service_stage === 'follow_up' && $ticket->vaccinationSchedule
            ? $ticket->vaccinationSchedule
            : VaccineAuthorization::dueDose($patient);
        if (! $due) {
            return [null, ['routing_option' => 'No scheduled dose is currently due for this patient. Verify the provider-ordered schedule and dose eligibility.']];
        }
        if ($reason = VaccineAuthorization::planError($patient, $due)) {
            return [$due, ['routing_option' => $reason]];
        }
        if (! config('vaccination.administration_sites', [])) {
            return [$due, ['routing_option' => 'Clinic-approved administration sites are not configured.']];
        }
        $hasBatch = VaccineInventory::where('vaccine_name', $patient->vaccine_type)
            ->where('quantity', '>', 0)->whereDate('expiration_date', '>=', today())->exists();
        if (! $hasBatch) {
            return [$due, ['routing_option' => 'No matching unexpired vaccine batch has stock available. Verify vaccine inventory before proceeding.']];
        }

        return [$due, []];
    }

    /** Blocking problems for the selected routing option, shown before confirmation. */
    private function blockingErrors(NurseAssessment $assessment, Ticket $ticket): array
    {
        return match ($assessment->routing_option) {
            'on_hold' => filled($assessment->nursing_remarks)
                ? []
                : ['nursing_remarks' => 'Record the reason for the hold and the required next step before confirming.'],
            'vaccine_administration' => array_merge(
                $this->completionErrors($assessment, $ticket->patient),
                $this->vaccineRoutePlan($ticket)[1]
            ),
            default => array_merge(
                $this->completionErrors($assessment, $ticket->patient),
                in_array($ticket->service_stage, ['assessment', 'medical_review'], true)
                    ? []
                    : ['routing_option' => 'Follow-up dose tickets cannot be transferred to the doctor queue from this form.']
            ),
        };
    }

    /** Prerequisite checklist rendered on the form and review screens. */
    private function routingPrerequisites(Ticket $ticket, NurseAssessment $assessment): array
    {
        $patient = $ticket->patient;
        $incident = $patient?->biteIncidents->sortBy('date_of_bite')->first();
        $checks = [
            ['label' => 'Original queue ticket', 'ok' => true, 'detail' => "{$ticket->ticket_number} · ".ucfirst(str_replace('_', ' ', $ticket->service_stage)).' stage'],
            ['label' => 'Assessment completion', 'ok' => ! $this->completionErrors($assessment), 'detail' => $this->completionErrors($assessment) === [] ? 'Required assessment fields are complete.' : 'Required assessment fields are still missing.'],
        ];

        if ($assessment->routing_option === 'vaccine_administration') {
            [$due] = $this->vaccineRoutePlan($ticket);
            $checks[] = ['label' => 'Clinical order', 'ok' => filled($patient?->vaccine_type), 'detail' => filled($patient?->vaccine_type) ? $patient->vaccine_type.' · '.($patient->planned_dose_count ?: '?').' planned doses' : 'No doctor-ordered vaccine on the patient record.'];
            $checks[] = ['label' => 'Scheduled dose', 'ok' => (bool) $due, 'detail' => $due ? "Dose {$due->dose_number} due {$due->scheduled_date?->format('M j, Y')}" : 'No dose is currently due.'];
            $checks[] = ['label' => 'Exposure category', 'ok' => filled($incident?->exposure_category), 'detail' => filled($incident?->exposure_category) ? 'Category '.$incident->exposure_category : 'Pending doctor review.'];
            $sites = config('vaccination.administration_sites', []);
            $checks[] = ['label' => 'Approved administration sites', 'ok' => (bool) $sites, 'detail' => $sites ? implode(', ', $sites) : 'Not configured by the clinic.'];
            $batch = filled($patient?->vaccine_type) ? VaccineInventory::where('vaccine_name', $patient->vaccine_type)
                ->where('quantity', '>', 0)->whereDate('expiration_date', '>=', today())
                ->orderBy('expiration_date')->orderBy('id')->first() : null;
            $checks[] = ['label' => 'Vaccine stock', 'ok' => (bool) $batch, 'detail' => $batch ? "{$batch->batch_number} — {$batch->quantity} available, expires {$batch->expiration_date->format('M j, Y')}" : 'No unexpired in-stock batch for this vaccine.'];
            $checks[] = ['label' => 'Inventory effect', 'ok' => true, 'detail' => 'Opening the vaccine form does not deduct stock; quantity changes only when an administration is saved.'];
        } elseif ($assessment->routing_option === 'on_hold') {
            $checks[] = ['label' => 'Hold reason', 'ok' => filled($assessment->nursing_remarks), 'detail' => filled($assessment->nursing_remarks) ? 'Reason recorded in Additional Nursing Remarks.' : 'A reason is required in Additional Nursing Remarks.'];
            $checks[] = ['label' => 'Queue effect', 'ok' => true, 'detail' => 'The original ticket becomes On Hold; the patient is not marked assessed or completed.'];
        } else {
            $checks[] = ['label' => 'Patient registration', 'ok' => $patient && ! $patient->trashed() && $patient->first_name && $patient->last_name && $patient->dob && $patient->sex && $patient->address && $patient->barangay, 'detail' => 'Required demographics and bite incident details.'];
            $checks[] = ['label' => 'Doctor queue transfer', 'ok' => in_array($ticket->service_stage, ['assessment', 'medical_review'], true), 'detail' => in_array($ticket->service_stage, ['assessment', 'medical_review'], true) ? 'The original ticket moves to the shared Doctor Waiting List without a duplicate.' : 'Follow-up dose tickets use the vaccine administration route instead.'];
        }

        return $checks;
    }
}
