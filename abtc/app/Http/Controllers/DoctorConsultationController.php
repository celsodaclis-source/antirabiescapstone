<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\DoctorStation;
use App\Models\Ticket;
use App\Models\VaccineInventory;
use App\Models\VaccinationSchedule;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DoctorConsultationController extends Controller
{
    private const DISPOSITIONS = ['route_to_nurse', 'complete_visit', 'on_hold', 'refer'];
    private const SCHEDULES = [
        'days_0_3_7' => [0, 3, 7],
        'days_0_3_7_28' => [0, 3, 7, 28],
    ];

    public function form(Ticket $ticket): View
    {
        [$station, $patient, $incident, $consultation] = $this->activeConsultationContext($ticket);
        return view('doctor.consultation', [
            'ticket' => $ticket, 'station' => $station, 'patient' => $patient,
            'incident' => $incident, 'consultation' => $consultation,
            'vaccineTypes' => collect($this->availableVaccineTypes())
                ->when(filled($patient->vaccine_type), fn ($types) => $types->push($patient->vaccine_type))
                ->unique()->values()->all(),
            'existingOrder' => [
                'vaccine_type' => $patient->vaccine_type,
                'planned_dose_count' => $patient->planned_dose_count,
                'schedule_code' => match ((int) $patient->planned_dose_count) {
                    3 => 'days_0_3_7', 4 => 'days_0_3_7_28', default => null,
                },
            ],
            'routes' => config('vaccination.administration_routes', []),
            'scheduleOptions' => $this->scheduleOptions(),
        ]);
    }

    public function saveDraft(Request $request, Ticket $ticket): RedirectResponse
    {
        [$station] = $this->activeConsultationContext($ticket);
        $validated = Validator::make($request->all(), $this->draftRules())->validate();
        $consultation = $this->persistDraft($ticket, $station, $request->user()->id, $validated);

        return redirect()->route('doctor.station.consultation.form', $ticket)
            ->with('status', 'Consultation draft saved. No queue status was changed.');
    }

    public function review(Request $request, Ticket $ticket): RedirectResponse
    {
        [$station] = $this->activeConsultationContext($ticket);
        $draft = Validator::make($request->all(), $this->draftRules())->validate();
        $this->persistDraft($ticket, $station, $request->user()->id, $draft);

        return redirect()->route('doctor.station.consultation.review', $ticket);
    }

    public function reviewPage(Ticket $ticket): View
    {
        [$station, $patient, $incident, $consultation] = $this->activeConsultationContext($ticket);
        abort_unless($consultation, 404, 'Save the consultation draft before reviewing it.');
        $errors = $this->finalizationErrors($consultation);

        return view('doctor.consultation-review', [
            'ticket' => $ticket, 'station' => $station, 'patient' => $patient,
            'incident' => $incident, 'consultation' => $consultation,
            'missingErrors' => $errors,
        ]);
    }

    public function finalize(Request $request, Ticket $ticket): RedirectResponse
    {
        $request->validate(['confirmed' => ['accepted']]);
        $doctor = $request->user();

        try {
            $message = DB::transaction(function () use ($ticket, $doctor) {
                $station = DoctorStation::where('user_id', $doctor->id)->lockForUpdate()->first();
                if (! $station) throw ValidationException::withMessages(['consultation' => 'You must be assigned to a doctor station.']);
                $lockedTicket = Ticket::with(['patient.biteIncidents', 'patient.vaccinationSchedules'])
                    ->lockForUpdate()->findOrFail($ticket->id);
                $this->assertActiveTicket($lockedTicket, $doctor->id, $station->id);
                $consultation = Consultation::where('ticket_id', $lockedTicket->id)->lockForUpdate()->first();
                if (! $consultation || $consultation->doctor_id !== $doctor->id || $consultation->doctor_station_id !== $station->id) {
                    throw ValidationException::withMessages(['consultation' => 'Save a consultation draft for this assigned patient before finalizing.']);
                }
                if (! in_array($consultation->status, ['draft', 'on_hold', 'referred'], true)) {
                    throw ValidationException::withMessages(['consultation' => 'This consultation has already been finalized.']);
                }
                $errors = $this->finalizationErrors($consultation);
                if ($errors !== []) throw ValidationException::withMessages($errors);

                $patient = $lockedTicket->patient;
                $incident = $patient->biteIncidents->sortBy('date_of_bite')->first();
                $incident->update(['exposure_category' => $consultation->exposure_category]);
                $this->saveOrderedPlanIfPresent($patient, $incident, $consultation);

                $status = match ($consultation->disposition) {
                    'complete_visit' => 'completed',
                    'route_to_nurse', 'on_hold', 'refer' => 'waiting',
                };
                $serviceStage = $consultation->disposition === 'route_to_nurse' ? 'follow_up' : $lockedTicket->service_stage;
                $doseId = $consultation->disposition === 'route_to_nurse'
                    ? $patient->vaccinationSchedules()->where('dose_number', 1)->value('id')
                    : $lockedTicket->vaccination_schedule_id;
                if (in_array($consultation->disposition, ['on_hold', 'refer'], true)) $status = 'held';

                $lockedTicket->update([
                    'status' => $status,
                    'service_stage' => $serviceStage,
                    'vaccination_schedule_id' => $doseId,
                    'completed_at' => $status === 'completed' ? now() : null,
                    // Keep completed ticket attribution for the doctor's daily KPIs and audit history.
                    'assigned_staff_id' => $status === 'completed' ? $doctor->id : null,
                    'counter_assigned' => $status === 'completed' ? $station->id : null,
                    'doctor_station_id' => $status === 'completed' ? $station->id : null,
                ]);
                $consultation->update([
                    'status' => match ($consultation->disposition) {
                        'complete_visit' => 'completed', 'route_to_nurse' => 'routed_to_nurse',
                        'on_hold' => 'on_hold', 'refer' => 'referred',
                    },
                    'finalized_at' => now(),
                ]);
                Audit::log('doctor.consultation.finalize', "Doctor finalized {$lockedTicket->ticket_number}: {$consultation->disposition}", $consultation);

                return match ($consultation->disposition) {
                    'route_to_nurse' => "Consultation saved. {$lockedTicket->ticket_number} was routed to the nurse treatment queue.",
                    'complete_visit' => "Consultation saved. Visit {$lockedTicket->ticket_number} is complete.",
                    'on_hold' => "Consultation saved. {$lockedTicket->ticket_number} is on hold for further assessment.",
                    'refer' => "Consultation saved. {$lockedTicket->ticket_number} was recorded for referral.",
                };
            });
        } catch (ValidationException $exception) {
            return redirect()->route('doctor.station.consultation.form', $ticket)
                ->withErrors($exception->errors());
        }

        return redirect()->route('doctor.station.show')->with('status', $message);
    }

    private function activeConsultationContext(Ticket $ticket): array
    {
        $doctor = request()->user();
        $station = DoctorStation::where('user_id', $doctor->id)->first();
        abort_unless($station, 403, 'Open your assigned doctor station first.');
        $ticket->load(['patient.biteIncidents', 'patient.vaccinationSchedules', 'assessmentCompletedBy', 'consultation', 'nurseAssessment']);
        $this->assertActiveTicket($ticket, $doctor->id, $station->id);
        if ($ticket->consultation && $ticket->consultation->doctor_id !== $doctor->id) abort(403, 'This consultation belongs to another doctor.');
        $patient = $ticket->patient;
        $incident = $patient?->biteIncidents->sortBy('date_of_bite')->first();
        abort_unless($patient && $incident, 422, 'The patient bite incident record is missing.');
        return [$station, $patient, $incident, $ticket->consultation];
    }

    private function assertActiveTicket(Ticket $ticket, int $doctorId, int $stationId): void
    {
        if ($ticket->status !== 'in_progress' || $ticket->assigned_staff_id !== $doctorId
            || ($ticket->doctor_station_id !== $stationId && ! ($ticket->doctor_station_id === null && $ticket->counter_assigned === $stationId))) {
            abort(403, 'This patient is not assigned to your active doctor station.');
        }
        if ($ticket->service_stage === 'follow_up') abort(422, 'Follow-up dose tickets are handled through the Nurse My Queue workflow.');
        abort_unless($ticket->assessment_completed_at, 422, 'The nurse assessment must be completed before consultation.');
    }

    private function draftRules(): array
    {
        $vaccineTypes = $this->availableVaccineTypes();
        return [
            'clinical_narrative' => ['nullable', 'string', 'max:10000'],
            'exposure_category' => ['nullable', Rule::in(['I', 'II', 'III'])],
            'vaccine_type' => ['nullable', Rule::in($vaccineTypes)],
            'schedule_code' => ['nullable', Rule::in(array_keys(self::SCHEDULES))],
            'vaccination_schedule' => ['nullable', 'string', 'max:500'],
            'planned_dose_count' => ['nullable', 'integer', Rule::in([3, 4])],
            'additional_instructions' => ['nullable', 'string', 'max:4000'],
            'disposition' => ['nullable', Rule::in(self::DISPOSITIONS)],
            'disposition_remarks' => ['nullable', 'string', 'max:4000'],
            'referral_destination' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function persistDraft(Ticket $ticket, DoctorStation $station, int $doctorId, array $data): Consultation
    {
        return DB::transaction(function () use ($ticket, $station, $doctorId, $data) {
            $locked = Ticket::lockForUpdate()->findOrFail($ticket->id);
            $this->assertActiveTicket($locked, $doctorId, $station->id);
            $consultation = Consultation::firstOrNew(['ticket_id' => $locked->id]);
            if ($consultation->exists && ($consultation->doctor_id !== $doctorId || ! in_array($consultation->status, ['draft', 'on_hold', 'referred'], true))) {
                throw ValidationException::withMessages(['consultation' => 'This consultation cannot be changed by this doctor.']);
            }
            $consultation->fill(array_merge($data, [
                'patient_id' => $locked->patient_id, 'doctor_id' => $doctorId,
                'doctor_station_id' => $station->id, 'status' => 'draft', 'draft_saved_at' => now(),
                'regimen_route' => ! empty($data['vaccine_type']) ? config('vaccination.administration_routes.'.$data['vaccine_type']) : null,
            ]));
            $consultation->save();
            Audit::log('doctor.consultation.draft', "Saved consultation draft for {$locked->ticket_number}", $consultation);
            return $consultation;
        });
    }

    private function finalizationErrors(Consultation $consultation): array
    {
        $errors = [];
        if (! filled($consultation->clinical_narrative)) $errors['clinical_narrative'] = 'Enter the clinical assessment and findings.';
        if (! in_array($consultation->exposure_category, ['I', 'II', 'III'], true)) $errors['exposure_category'] = 'Select an approved exposure category.';
        if (! in_array($consultation->disposition, self::DISPOSITIONS, true)) $errors['disposition'] = 'Choose one supported patient disposition.';
        if (in_array($consultation->disposition, ['route_to_nurse', 'on_hold', 'refer'], true)
            && ! filled($consultation->disposition_remarks)) {
            $errors['disposition_remarks'] = 'Enter instructions or the reason for this disposition.';
        }
        if ($consultation->disposition === 'refer' && ! filled($consultation->referral_destination)) $errors['referral_destination'] = 'Enter the referral destination.';

        $hasPlan = filled($consultation->vaccine_type) || filled($consultation->schedule_code) || filled($consultation->planned_dose_count);
        if ($consultation->disposition === 'route_to_nurse' || $hasPlan) {
            $types = $this->availableVaccineTypes();
            if (! in_array($consultation->vaccine_type, $types, true)) $errors['vaccine_type'] = 'Select a vaccine currently available in the approved inventory list.';
            $days = self::SCHEDULES[$consultation->schedule_code] ?? null;
            if (! $days) $errors['schedule_code'] = 'Select a supported provider-ordered schedule.';
            else {
                $doseCount = count($days);
                $expected = $this->scheduleText($days);
                if ((int) $consultation->planned_dose_count !== $doseCount) $errors['planned_dose_count'] = 'The dose count must match the selected provider-ordered schedule.';
                if ($consultation->vaccination_schedule !== $expected) $errors['vaccination_schedule'] = 'The schedule text must match the selected approved regimen.';
            }
            if (filled($consultation->vaccine_type) && ! config('vaccination.administration_routes.'.$consultation->vaccine_type)) {
                $errors['regimen_route'] = 'The clinic has no approved route configured for this vaccine.';
            }
            if ($consultation->disposition === 'route_to_nurse') {
                if (! config('vaccination.administration_sites', [])) $errors['disposition'] = 'Configure approved nurse administration sites before routing a patient for vaccination.';
                if (filled($consultation->vaccine_type) && ! VaccineInventory::where('vaccine_name', $consultation->vaccine_type)
                    ->where('quantity', '>', 0)->whereDate('expiration_date', '>=', today())->exists()) {
                    $errors['vaccine_type'] = 'No unexpired batch with stock is available for this vaccine.';
                }
            }
        }
        return $errors;
    }

    private function saveOrderedPlanIfPresent($patient, $incident, Consultation $consultation): void
    {
        if (! filled($consultation->vaccine_type)) return;
        $days = self::SCHEDULES[$consultation->schedule_code];
        $existing = $patient->vaccinationSchedules()->withCount('tickets')->orderBy('dose_number')->lockForUpdate()->get();
        if ($existing->isNotEmpty()) {
            if ($existing->contains(fn ($dose) => $dose->status !== 'scheduled' || $dose->tickets_count > 0)) {
                if ($patient->vaccine_type !== $consultation->vaccine_type
                    || (int) $patient->planned_dose_count !== (int) $consultation->planned_dose_count
                    || $existing->count() !== count($days)) {
                    throw ValidationException::withMessages(['vaccination_schedule' => 'The existing dose schedule has activity and cannot be replaced during this consultation.']);
                }
            } elseif ($existing->count() !== count($days)) {
                $patient->vaccinationSchedules()->delete();
                $existing = collect();
            }
        }
        $patient->update(['vaccine_type' => $consultation->vaccine_type, 'planned_dose_count' => count($days)]);
        if ($existing->isEmpty()) {
            foreach ($days as $index => $offset) {
                VaccinationSchedule::create([
                    'patient_id' => $patient->id, 'dose_number' => $index + 1,
                    'scheduled_date' => $incident->date_of_bite->copy()->addDays($offset), 'status' => 'scheduled',
                ]);
            }
        } else {
            foreach ($existing as $index => $dose) {
                $dose->update(['scheduled_date' => $incident->date_of_bite->copy()->addDays($days[$index])]);
            }
        }
    }

    private function availableVaccineTypes(): array
    {
        return VaccineInventory::where('quantity', '>', 0)->whereDate('expiration_date', '>=', today())
            ->pluck('vaccine_name')->unique()->reject(fn (string $name) => (bool) preg_match(
                '/\\b(?:equi[\\s-]?rab|(?:human|equine)\\s+rabies\\s+(?:immunoglobulin|serum)|anti[- ]rabies\\s+serum|rabies\\s+(?:immunoglobulin|immune\\s+globulin)|(?:hrig|erig|rig))\\b/i', $name
            ))->values()->all();
    }

    private function scheduleOptions(): array
    {
        return collect(self::SCHEDULES)->map(fn ($days, $code) => [
            'code' => $code, 'dose_count' => count($days), 'label' => $this->scheduleText($days), 'days' => $days,
        ])->values()->all();
    }

    private function scheduleText(array $days): string
    {
        return collect($days)->map(fn ($day) => 'Day '.$day)->implode(', ');
    }
}
