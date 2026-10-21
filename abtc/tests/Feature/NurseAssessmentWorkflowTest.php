<?php

namespace Tests\Feature;

use App\Models\BiteIncident;
use App\Models\NurseStation;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NurseAssessmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_form_loads_the_original_ticket_and_patient_and_restores_the_saved_draft(): void
    {
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $station = $this->assignStation($nurse, 'Nurse 1');
        $patient = $this->patientWithBite('Grace');
        $ticket = $this->assessmentTicket($patient, $nurse, $station);

        $this->actingAs($nurse)->get(route('nurse.station.assessment.form', $ticket))->assertOk()
            ->assertSee('Nurse Assessment')->assertSee($station->name)
            ->assertSee($ticket->ticket_number)->assertSee($patient->fullName())
            ->assertSee('Patient #'.$patient->id)->assertSee('New Patient')
            ->assertSee('Blood Pressure')->assertSee('Respiratory Rate')
            ->assertSee('Assessment and Observations')->assertSee('Send to Doctor')
            ->assertSee('Review Workflow Rules');

        $this->post(route('nurse.station.assessment.draft', $ticket), [
            'blood_pressure' => '120/80', 'temperature' => '36.8', 'pulse_rate' => '78',
            'respiratory_rate' => '18', 'weight' => '62.5',
            'bite_site' => 'Right forearm', 'animal_status' => 'Unknown', 'animal_vaccinated' => '0',
            'assessment_observations' => 'Patient reports mild pain at the wound site.',
            'nursing_remarks' => 'Wound cleaned and dressed.',
            'routing_option' => 'send_to_doctor',
        ])->assertRedirect(route('nurse.station.assessment.form', $ticket));

        $this->assertDatabaseHas('nurse_assessments', [
            'ticket_id' => $ticket->id, 'patient_id' => $patient->id, 'nurse_id' => $nurse->id,
            'nurse_station_id' => $station->id, 'status' => 'draft', 'blood_pressure' => '120/80',
            'pulse_rate' => 78, 'respiratory_rate' => 18, 'routing_option' => 'send_to_doctor',
        ]);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'status' => 'in_progress', 'assessment_completed_at' => null,
        ]);

        $this->get(route('nurse.station.assessment.form', $ticket))->assertOk()
            ->assertSee('120/80')->assertSee('Patient reports mild pain at the wound site.')
            ->assertSee('Wound cleaned and dressed.')->assertSee('In Assessment');
    }

    public function test_malformed_or_unsafe_vital_signs_are_rejected_and_valid_ones_are_saved(): void
    {
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $station = $this->assignStation($nurse, 'Nurse 1');
        $patient = $this->patientWithBite('Vitals');
        $ticket = $this->assessmentTicket($patient, $nurse, $station);

        $this->actingAs($nurse)->post(route('nurse.station.assessment.draft', $ticket), [
            'blood_pressure' => 'not-a-pressure',
        ])->assertSessionHasErrors('blood_pressure');

        $this->post(route('nurse.station.assessment.draft', $ticket), [
            'blood_pressure' => '90/140',
        ])->assertSessionHasErrors('blood_pressure');

        $this->post(route('nurse.station.assessment.draft', $ticket), [
            'temperature' => '99', 'pulse_rate' => '5', 'respiratory_rate' => '200', 'weight' => '900',
        ])->assertSessionHasErrors(['temperature', 'pulse_rate', 'respiratory_rate', 'weight']);

        $this->post(route('nurse.station.assessment.draft', $ticket), [
            'blood_pressure' => '120/80 mmHg', 'temperature' => '37.2', 'pulse_rate' => '84',
            'respiratory_rate' => '20', 'weight' => '70',
        ])->assertRedirect(route('nurse.station.assessment.form', $ticket));

        $this->assertDatabaseHas('nurse_assessments', [
            'ticket_id' => $ticket->id, 'blood_pressure' => '120/80 mmHg',
            'temperature' => 37.2, 'pulse_rate' => 84, 'respiratory_rate' => 20, 'weight' => 70.00,
        ]);
    }

    public function test_incomplete_assessment_cannot_be_marked_completed(): void
    {
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $station = $this->assignStation($nurse, 'Nurse 1');
        $patient = $this->patientWithBite('Incomplete');
        $ticket = $this->assessmentTicket($patient, $nurse, $station);

        $this->actingAs($nurse)->post(route('nurse.station.assessment.review', $ticket), [
            'blood_pressure' => '118/76', 'bite_site' => 'Left leg',
            'animal_status' => 'Healthy', 'animal_vaccinated' => '1',
            'routing_option' => 'send_to_doctor',
        ])->assertRedirect(route('nurse.station.assessment.review-page', $ticket));

        $this->post(route('nurse.station.assessment.finalize', $ticket), [
            'confirmed' => '1', 'routing_option' => 'send_to_doctor',
        ])->assertRedirect(route('nurse.station.assessment.review-page', $ticket))
            ->assertSessionHasErrors('assessment_observations');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'status' => 'in_progress', 'assessment_completed_at' => null,
        ]);
        $this->assertDatabaseHas('nurse_assessments', ['ticket_id' => $ticket->id, 'status' => 'draft']);
    }

    public function test_send_to_doctor_moves_the_original_ticket_to_the_doctor_queue_without_duplicates(): void
    {
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $doctor = User::factory()->create(['role' => 'Doctor']);
        $admin = User::factory()->create(['role' => 'Admin']);
        $station = $this->assignStation($nurse, 'Nurse 1');
        $patient = $this->patientWithBite('Transfer');
        $ticket = $this->assessmentTicket($patient, $nurse, $station);

        $this->actingAs($nurse)->post(route('nurse.station.assessment.review', $ticket), [
            'blood_pressure' => '124/82', 'temperature' => '36.9', 'pulse_rate' => '76',
            'respiratory_rate' => '18', 'weight' => '58.4',
            'bite_site' => 'Right forearm', 'animal_status' => 'Unknown', 'animal_vaccinated' => '0',
            'assessment_observations' => 'Alert and oriented; wound cleaned, no signs of infection.',
            'nursing_remarks' => 'Advise dog observation guidance.',
            'routing_option' => 'send_to_doctor',
        ])->assertRedirect(route('nurse.station.assessment.review-page', $ticket));

        $this->get(route('nurse.station.assessment.review-page', $ticket))->assertOk()
            ->assertSee('Review Workflow Rules')->assertSee('Send to Doctor')
            ->assertSee('124/82')->assertSee('Alert and oriented');

        $this->post(route('nurse.station.assessment.finalize', $ticket), [
            'confirmed' => '1', 'routing_option' => 'send_to_doctor',
        ])->assertRedirect(route('nurse.station.show'))->assertSessionHas('status');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'status' => 'waiting', 'assessment_completed_by' => $nurse->id,
            'assigned_staff_id' => null, 'counter_assigned' => null,
        ]);
        $this->assertNotNull($ticket->fresh()->assessment_completed_at);
        $this->assertDatabaseHas('nurse_assessments', [
            'ticket_id' => $ticket->id, 'status' => 'completed', 'routing_option' => 'send_to_doctor',
            'nurse_id' => $nurse->id, 'assessment_observations' => 'Alert and oriented; wound cleaned, no signs of infection.',
        ]);
        $this->assertDatabaseHas('bite_incidents', [
            'patient_id' => $patient->id, 'bite_site' => 'Right forearm', 'animal_status' => 'Unknown', 'animal_vaccinated' => 0,
        ]);
        $this->assertSame(1, Ticket::where('patient_id', $patient->id)->count(), 'No duplicate ticket is created.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'nurse.assessment_complete', 'user_id' => $nurse->id]);

        $this->actingAs($admin)->get(route('queue-monitoring.data'))
            ->assertJsonPath('stats.nurse_serving', 0)->assertJsonPath('stats.doctor_waiting', 1);

        $doctorStation = $this->assignDoctorStation($doctor, 'Doctor 1');
        $this->actingAs($doctor)->get(route('doctor.station.show'))->assertOk()->assertSee($ticket->ticket_number);
        $this->post(route('doctor.station.call-next'))->assertSessionHas('status');
        $this->get(route('doctor.station.consultation.form', $ticket))->assertOk()
            ->assertSee('124/82')->assertSee('Alert and oriented; wound cleaned, no signs of infection.')
            ->assertSee('Advise dog observation guidance.');

        $this->post(route('nurse.station.assessment.finalize', $ticket), [
            'confirmed' => '1', 'routing_option' => 'send_to_doctor',
        ])->assertForbidden();
    }

    public function test_vaccine_routing_requires_an_authorized_plan_and_never_deducts_stock(): void
    {
        config()->set('vaccination.administration_sites', ['Left deltoid']);
        config()->set('vaccination.administration_routes.PVRV', 'IM');
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $station = $this->assignStation($nurse, 'Nurse 1');
        $batch = VaccineInventory::create([
            'batch_number' => 'ASSESS-VAX-01', 'vaccine_name' => 'PVRV', 'quantity' => 2,
            'expiration_date' => today()->addYear()->toDateString(), 'low_stock_threshold' => 1,
        ]);
        $patient = $this->patientWithOrder();
        $schedule = $patient->vaccinationSchedules()->where('dose_number', 1)->firstOrFail();
        $ticket = Ticket::create([
            'patient_id' => $patient->id, 'ticket_number' => 'A-700', 'status' => 'in_progress',
            'visit_type' => 'new', 'service_stage' => 'assessment',
            'assigned_staff_id' => $nurse->id, 'counter_assigned' => $station->id,
        ]);

        $this->actingAs($nurse)->post(route('nurse.station.assessment.review', $ticket), [
            'bite_site' => 'Left arm', 'animal_status' => 'Healthy', 'animal_vaccinated' => '1',
            'assessment_observations' => 'Plan verified against the doctor order.',
            'routing_option' => 'vaccine_administration',
        ])->assertRedirect(route('nurse.station.assessment.review-page', $ticket));

        $this->post(route('nurse.station.assessment.finalize', $ticket), [
            'confirmed' => '1', 'routing_option' => 'vaccine_administration',
        ])->assertRedirect(route('nurse.station.vaccine.form', $ticket))->assertSessionHas('status');

        $freshTicket = $ticket->fresh();
        $this->assertSame('in_progress', $freshTicket->status);
        $this->assertSame('follow_up', $freshTicket->service_stage);
        $this->assertSame($schedule->id, $freshTicket->vaccination_schedule_id);
        $this->assertSame($nurse->id, $freshTicket->assigned_staff_id);
        $this->assertSame($station->id, $freshTicket->counter_assigned);
        $this->assertNotNull($freshTicket->assessment_completed_at);
        $this->assertSame('scheduled', $schedule->fresh()->status, 'Routing must not record administration.');
        $this->assertSame(2, $batch->fresh()->quantity, 'Routing must not deduct inventory.');
        $this->assertDatabaseHas('nurse_assessments', [
            'ticket_id' => $ticket->id, 'status' => 'completed', 'routing_option' => 'vaccine_administration',
        ]);
        $this->get(route('nurse.station.vaccine.form', $ticket))->assertOk()->assertSee('Vaccine Administration');
    }

    public function test_vaccine_routing_is_blocked_without_a_doctor_ordered_plan(): void
    {
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $station = $this->assignStation($nurse, 'Nurse 1');
        $patient = $this->patientWithBite('NoPlan');
        $ticket = $this->assessmentTicket($patient, $nurse, $station);

        $this->actingAs($nurse)->post(route('nurse.station.assessment.review', $ticket), [
            'bite_site' => 'Left arm', 'animal_status' => 'Healthy', 'animal_vaccinated' => '1',
            'assessment_observations' => 'Awaiting the doctor order before any vaccination.',
            'routing_option' => 'vaccine_administration',
        ])->assertRedirect(route('nurse.station.assessment.review-page', $ticket));

        $this->get(route('nurse.station.assessment.review-page', $ticket))->assertOk()
            ->assertSee('doctor-ordered vaccine plan');

        $this->post(route('nurse.station.assessment.finalize', $ticket), [
            'confirmed' => '1', 'routing_option' => 'vaccine_administration',
        ])->assertRedirect(route('nurse.station.assessment.review-page', $ticket))
            ->assertSessionHasErrors('routing_option');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'status' => 'in_progress', 'service_stage' => 'assessment',
            'assessment_completed_at' => null, 'vaccination_schedule_id' => null,
        ]);
        $this->assertDatabaseHas('nurse_assessments', ['ticket_id' => $ticket->id, 'status' => 'draft']);
    }

    public function test_on_hold_preserves_the_ticket_and_the_saved_draft_until_resumed(): void
    {
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $admin = User::factory()->create(['role' => 'Admin']);
        $station = $this->assignStation($nurse, 'Nurse 1');
        $patient = $this->patientWithBite('Hold');
        $ticket = $this->assessmentTicket($patient, $nurse, $station);

        $this->actingAs($nurse)->post(route('nurse.station.assessment.review', $ticket), [
            'blood_pressure' => '130/85', 'bite_site' => 'Right hand',
            'animal_status' => 'Sick', 'animal_vaccinated' => '0',
            'assessment_observations' => 'Awaiting animal status confirmation from the owner.',
            'routing_option' => 'on_hold',
        ])->assertRedirect(route('nurse.station.assessment.review-page', $ticket));

        $this->post(route('nurse.station.assessment.finalize', $ticket), [
            'confirmed' => '1', 'routing_option' => 'on_hold',
        ])->assertRedirect(route('nurse.station.assessment.review-page', $ticket))
            ->assertSessionHasErrors('nursing_remarks');

        $this->post(route('nurse.station.assessment.review', $ticket), [
            'blood_pressure' => '130/85', 'bite_site' => 'Right hand',
            'animal_status' => 'Sick', 'animal_vaccinated' => '0',
            'assessment_observations' => 'Awaiting animal status confirmation from the owner.',
            'nursing_remarks' => 'Call owner tomorrow for animal observation update.',
            'routing_option' => 'on_hold',
        ])->assertRedirect(route('nurse.station.assessment.review-page', $ticket));

        $this->post(route('nurse.station.assessment.finalize', $ticket), [
            'confirmed' => '1', 'routing_option' => 'on_hold',
        ])->assertRedirect(route('nurse.station.show'))->assertSessionHas('status');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'status' => 'held', 'assessment_completed_at' => null,
            'assigned_staff_id' => null, 'counter_assigned' => null,
        ]);
        $this->assertDatabaseHas('nurse_assessments', [
            'ticket_id' => $ticket->id, 'status' => 'on_hold', 'routing_option' => 'on_hold',
            'nursing_remarks' => 'Call owner tomorrow for animal observation update.', 'completed_at' => null,
        ]);
        $this->assertDatabaseHas('patients', ['id' => $patient->id]);
        $this->actingAs($admin)->get(route('queue-monitoring.data'))
            ->assertJsonPath('stats.on_hold', 1)->assertJsonPath('stats.nurse_serving', 0);

        $this->actingAs($nurse)->get(route('nurse.station.show'))->assertOk()
            ->assertSee('On Hold — Awaiting Further Assessment')->assertSee('Call owner tomorrow');
        $this->post(route('nurse.station.assessment.resume', $ticket))
            ->assertRedirect(route('nurse.station.assessment.form', $ticket))->assertSessionHas('status');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'status' => 'in_progress', 'assigned_staff_id' => $nurse->id, 'counter_assigned' => $station->id,
        ]);
        $this->get(route('nurse.station.assessment.form', $ticket))->assertOk()
            ->assertSee('130/85')->assertSee('Awaiting animal status confirmation from the owner.')
            ->assertSee('Call owner tomorrow for animal observation update.');
    }

    public function test_only_the_assigned_nurse_and_station_can_access_and_update_the_assessment(): void
    {
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $otherNurse = User::factory()->create(['role' => 'Nurse']);
        $doctor = User::factory()->create(['role' => 'Doctor']);
        $station = $this->assignStation($nurse, 'Nurse 1');
        $otherStation = $this->assignStation($otherNurse, 'Nurse 2');
        $patient = $this->patientWithBite('Secure');
        $ticket = $this->assessmentTicket($patient, $nurse, $station);

        $this->actingAs($doctor)->get(route('nurse.station.assessment.form', $ticket))->assertForbidden();
        $this->actingAs($otherNurse)->get(route('nurse.station.assessment.form', $ticket))->assertForbidden();
        $this->actingAs($otherNurse)->post(route('nurse.station.assessment.draft', $ticket), [
            'assessment_observations' => 'Unauthorized entry.',
        ])->assertForbidden();
        $this->actingAs($otherNurse)->post(route('nurse.station.assessment.finalize', $ticket), [
            'confirmed' => '1', 'routing_option' => 'send_to_doctor',
        ])->assertForbidden();

        $stationless = User::factory()->create(['role' => 'Nurse']);
        $this->actingAs($stationless)->get(route('nurse.station.assessment.form', $ticket))->assertForbidden();

        $this->assertDatabaseMissing('nurse_assessments', ['ticket_id' => $ticket->id]);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'in_progress', 'counter_assigned' => $station->id]);
        $this->assertSame($otherNurse->id, $otherStation->fresh()->user_id);
    }

    public function test_review_page_requires_a_saved_draft(): void
    {
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $station = $this->assignStation($nurse, 'Nurse 1');
        $patient = $this->patientWithBite('Review');
        $ticket = $this->assessmentTicket($patient, $nurse, $station);

        $this->actingAs($nurse)->get(route('nurse.station.assessment.review-page', $ticket))->assertNotFound();
    }

    private function assignStation(User $nurse, string $name): NurseStation
    {
        $station = NurseStation::where('name', $name)->firstOrFail();
        $station->update(['user_id' => $nurse->id]);

        return $station;
    }

    private function assignDoctorStation(User $doctor, string $name): \App\Models\DoctorStation
    {
        $station = \App\Models\DoctorStation::where('name', $name)->firstOrFail();
        $station->update(['user_id' => $doctor->id]);

        return $station;
    }

    private function patientWithBite(string $firstName): Patient
    {
        $patient = Patient::create([
            'first_name' => $firstName, 'last_name' => 'Assessment', 'dob' => '1995-04-12', 'sex' => 'Female',
            'address' => '1 Test Road', 'barangay' => 'Barangka', 'contact_number' => '09170000000',
        ]);
        BiteIncident::create([
            'patient_id' => $patient->id, 'date_of_bite' => today()->toDateString(), 'animal_type' => 'Dog',
        ]);

        return $patient;
    }

    private function patientWithOrder(): Patient
    {
        $patient = Patient::create([
            'first_name' => 'Ordered', 'last_name' => 'Patient', 'dob' => '1990-01-01', 'sex' => 'Male',
            'address' => 'Test Road', 'barangay' => 'Barangka', 'vaccine_type' => 'PVRV', 'planned_dose_count' => 3,
        ]);
        BiteIncident::create([
            'patient_id' => $patient->id, 'date_of_bite' => today()->subDays(3)->toDateString(),
            'animal_type' => 'Dog', 'exposure_category' => 'II',
        ]);
        foreach ([0, 3, 7] as $index => $day) {
            VaccinationSchedule::create([
                'patient_id' => $patient->id, 'dose_number' => $index + 1,
                'scheduled_date' => today()->subDays(3)->addDays($day)->toDateString(), 'status' => 'scheduled',
            ]);
        }

        return $patient;
    }

    private function assessmentTicket(Patient $patient, User $nurse, NurseStation $station): Ticket
    {
        return Ticket::create([
            'patient_id' => $patient->id, 'ticket_number' => 'A-601', 'status' => 'in_progress',
            'visit_type' => 'new', 'service_stage' => 'assessment',
            'assigned_staff_id' => $nurse->id, 'counter_assigned' => $station->id,
            'called_at' => now(), 'served_at' => now(),
        ]);
    }
}
