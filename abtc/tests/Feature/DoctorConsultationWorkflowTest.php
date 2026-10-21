<?php

namespace Tests\Feature;

use App\Models\BiteIncident;
use App\Models\Consultation;
use App\Models\DoctorStation;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VaccineInventory;
use App\Models\VaccinationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorConsultationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_doctor_sees_read_only_assessment_and_can_save_a_draft(): void
    {
        [$doctor, $ticket] = $this->activeConsultation();

        $this->actingAs($doctor)->get(route('doctor.station.consultation.form', $ticket))
            ->assertOk()->assertSee($ticket->ticket_number)->assertSee($ticket->patient->fullName())
            ->assertSee('Right forearm')->assertSee('Not recorded')->assertSee('readonly');

        $this->post(route('doctor.station.consultation.draft', $ticket), [
            'clinical_narrative' => 'Clinical findings recorded for review.',
            'disposition' => 'complete_visit',
        ])->assertRedirect(route('doctor.station.consultation.form', $ticket));

        $this->assertDatabaseHas('consultations', [
            'ticket_id' => $ticket->id, 'patient_id' => $ticket->patient_id,
            'doctor_id' => $doctor->id, 'status' => 'draft',
            'clinical_narrative' => 'Clinical findings recorded for review.',
        ]);
        $this->assertSame('in_progress', $ticket->fresh()->status, 'Saving a draft must not move the queue ticket.');
    }

    public function test_finalization_requires_clinic_decision_and_narrative_and_routes_original_ticket_to_nurse(): void
    {
        [$doctor, $ticket] = $this->activeConsultation(true);
        config()->set('vaccination.doctor_treatment_decisions', ['Rabies vaccination']);
        config()->set('vaccination.administration_routes', ['PVRV' => 'IM']);
        config()->set('vaccination.administration_sites', ['Left deltoid']);
        VaccineInventory::create([
            'batch_number' => 'CONSULT-1', 'vaccine_name' => 'PVRV', 'quantity' => 10,
            'expiration_date' => now()->addYear()->toDateString(), 'low_stock_threshold' => 1,
        ]);

        $invalid = $this->consultationPayload('route_to_nurse');
        $invalid['clinical_narrative'] = '';
        $this->actingAs($doctor)->post(route('doctor.station.consultation.review', $ticket), $invalid)
            ->assertRedirect(route('doctor.station.consultation.review-page', $ticket));
        $this->get(route('doctor.station.consultation.review-page', $ticket))
            ->assertOk()->assertSee('This consultation cannot be finalized yet.')->assertSee('clinical assessment and findings');
        $this->assertSame('in_progress', $ticket->fresh()->status);

        $this->post(route('doctor.station.consultation.review', $ticket), $this->consultationPayload('route_to_nurse'))
            ->assertRedirect(route('doctor.station.consultation.review-page', $ticket));
        $this->post(route('doctor.station.consultation.finalize', $ticket), ['confirmed' => '1'])
            ->assertRedirect(route('doctor.station.show'))->assertSessionHas('status');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'ticket_number' => $ticket->ticket_number,
            'status' => 'waiting', 'service_stage' => 'follow_up', 'assigned_staff_id' => null,
        ]);
        $this->assertSame(1, Ticket::where('patient_id', $ticket->patient_id)->count());
        $this->assertSame('routed_to_nurse', Consultation::where('ticket_id', $ticket->id)->value('status'));
        $this->assertSame(3, VaccinationSchedule::where('patient_id', $ticket->patient_id)->count());
        $this->assertSame(10, VaccineInventory::where('batch_number', 'CONSULT-1')->value('quantity'));
    }

    public function test_complete_visit_does_not_create_an_administered_dose_and_duplicate_finalize_is_rejected(): void
    {
        [$doctor, $ticket] = $this->activeConsultation();
        config()->set('vaccination.doctor_treatment_decisions', ['No vaccine indicated']);
        $payload = $this->consultationPayload('complete_visit');
        $payload['vaccine_type'] = '';
        $payload['schedule_code'] = '';
        $payload['planned_dose_count'] = '';
        $payload['vaccination_schedule'] = '';
        $payload['treatment_decision'] = 'No vaccine indicated';

        $this->actingAs($doctor)->post(route('doctor.station.consultation.review', $ticket), $payload)
            ->assertRedirect(route('doctor.station.consultation.review-page', $ticket));
        $this->post(route('doctor.station.consultation.finalize', $ticket), ['confirmed' => '1'])
            ->assertRedirect(route('doctor.station.show'));
        $this->assertSame('completed', $ticket->fresh()->status);
        $this->assertSame(0, VaccinationSchedule::where('patient_id', $ticket->patient_id)->where('status', 'administered')->count());

        $this->post(route('doctor.station.consultation.finalize', $ticket), ['confirmed' => '1'])
            ->assertForbidden();
    }

    public function test_another_doctor_cannot_open_or_edit_the_active_consultation(): void
    {
        [$doctor, $ticket] = $this->activeConsultation();
        $otherDoctor = User::factory()->create(['role' => 'Doctor']);
        $nurse = User::factory()->create(['role' => 'Nurse']);
        DoctorStation::where('name', 'Doctor 2')->update(['user_id' => $otherDoctor->id]);

        $this->actingAs($otherDoctor)->get(route('doctor.station.consultation.form', $ticket))->assertForbidden();
        $this->actingAs($otherDoctor)->post(route('doctor.station.consultation.draft', $ticket), [
            'clinical_narrative' => 'Unauthorized update',
        ])->assertForbidden();
        $this->actingAs($nurse)->get(route('doctor.station.consultation.form', $ticket))->assertForbidden();
        $this->assertDatabaseMissing('consultations', ['ticket_id' => $ticket->id]);
    }

    public function test_on_hold_and_referral_dispositions_remain_visible_as_held_work(): void
    {
        config()->set('vaccination.doctor_treatment_decisions', ['Rabies vaccination']);
        config()->set('vaccination.administration_routes', ['PVRV' => 'IM']);
        VaccineInventory::create([
            'batch_number' => 'HOLD-1', 'vaccine_name' => 'PVRV', 'quantity' => 10,
            'expiration_date' => now()->addYear()->toDateString(), 'low_stock_threshold' => 1,
        ]);

        foreach ([['on_hold', null], ['refer', 'City Hospital']] as [$disposition, $destination]) {
            [$doctor, $ticket] = $this->activeConsultation();
            $payload = $this->consultationPayload($disposition);
            if ($destination) $payload['referral_destination'] = $destination;

            $this->actingAs($doctor)->post(route('doctor.station.consultation.review', $ticket), $payload)
                ->assertRedirect(route('doctor.station.consultation.review-page', $ticket));
            $this->post(route('doctor.station.consultation.finalize', $ticket), ['confirmed' => '1'])
                ->assertRedirect(route('doctor.station.show'));

            $this->assertSame('held', $ticket->fresh()->status);
            $expected = $disposition === 'refer' ? 'referred' : 'on_hold';
            $this->assertSame($expected, Consultation::where('ticket_id', $ticket->id)->value('status'));
            if ($destination) $this->assertDatabaseHas('consultations', ['ticket_id' => $ticket->id, 'referral_destination' => $destination]);

            DoctorStation::where('user_id', $doctor->id)->update(['user_id' => null]);
        }
    }

    private function activeConsultation(bool $completedAssessment = true): array
    {
        $doctor = User::factory()->create(['role' => 'Doctor']);
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $station = DoctorStation::where('name', 'Doctor 1')->firstOrFail();
        $station->update(['user_id' => $doctor->id]);
        $patient = Patient::create([
            'first_name' => 'Jamie', 'last_name' => 'Test', 'dob' => '1990-01-01',
            'sex' => 'Female', 'address' => 'Test Road', 'barangay' => 'Barangka',
        ]);
        BiteIncident::create([
            'patient_id' => $patient->id, 'date_of_bite' => now()->toDateString(),
            'animal_type' => 'Dog', 'exposure_category' => 'II', 'bite_site' => 'Right forearm',
            'animal_status' => 'Unknown', 'animal_vaccinated' => false,
        ]);
        $ticket = Ticket::create([
            'patient_id' => $patient->id, 'ticket_number' => 'CONS-'.str_pad((string) (Ticket::count() + 25), 3, '0', STR_PAD_LEFT), 'status' => 'in_progress',
            'counter_assigned' => $station->id, 'assigned_staff_id' => $doctor->id,
            'doctor_station_id' => $station->id, 'called_at' => now(), 'served_at' => now(),
            'assessment_completed_by' => $completedAssessment ? $nurse->id : null,
            'assessment_completed_at' => $completedAssessment ? now() : null,
            'is_priority' => false, 'visit_type' => 'new', 'service_stage' => 'assessment',
        ]);

        return [$doctor, $ticket->fresh(['patient'])];
    }

    private function consultationPayload(string $disposition): array
    {
        return [
            'clinical_narrative' => 'Exposure and relevant clinical findings documented.',
            'exposure_category' => 'II', 'treatment_decision' => 'Rabies vaccination',
            'vaccine_type' => 'PVRV', 'schedule_code' => 'days_0_3_7',
            'planned_dose_count' => 3, 'vaccination_schedule' => 'Day 0, Day 3, Day 7',
            'additional_instructions' => 'Follow the ordered schedule.',
            'disposition' => $disposition, 'disposition_remarks' => 'Disposition and next steps explained.',
        ];
    }
}
