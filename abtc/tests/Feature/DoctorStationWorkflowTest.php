<?php

namespace Tests\Feature;

use App\Models\BiteIncident;
use App\Models\DoctorStation;
use App\Models\NurseStation;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VaccineInventory;
use App\Models\VaccinationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorStationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_nurse_assessment_queues_patient_for_doctor_and_consultation_completion_keeps_doses_scheduled(): void
    {
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $doctor = User::factory()->create(['role' => 'Doctor']);
        $admin = User::factory()->create(['role' => 'Admin']);
        VaccineInventory::create([
            'batch_number' => 'FLOW-001', 'vaccine_name' => 'PVRV', 'quantity' => 10,
            'expiration_date' => now()->addYear()->toDateString(), 'low_stock_threshold' => 1,
        ]);
        $this->actingAs($admin)->post(route('patients.store'), [
            'first_name' => 'Alex', 'last_name' => 'Workflow', 'dob' => '1990-01-01', 'sex' => 'Female', 'contact_number' => '',
            'address' => '1 Test Road', 'barangay' => 'Barangka', 'date_of_bite' => now()->toDateString(),
            'animal_type' => 'Dog', 'exposure_category' => 'II', 'register_queue' => '1', 'patient_type' => 'regular',
        ])->assertRedirect();
        $ticket = Ticket::latest('id')->firstOrFail();

        $nurseStation = NurseStation::where('name', 'Nurse 1')->firstOrFail();
        $this->actingAs($nurse)->post(route('nurse.stations.assign', $nurseStation))->assertRedirect();
        $this->get(route('nurse.station.show'))->assertOk()->assertSee('Waiting for Nurse Assessment')->assertSee($ticket->ticket_number);
        $this->actingAs($admin)->get(route('queue-monitoring.data'))->assertJsonPath('stats.nurse_waiting', 1)
            ->assertJsonPath('stats.doctor_waiting', 0);
        $this->actingAs($nurse);
        $this->post(route('nurse.station.call-next'))->assertRedirect()->assertSessionHas('status');
        $this->get(route('nurse.station.show'))->assertOk()->assertSee('Now Serving')->assertSee($ticket->ticket_number);
        $this->actingAs($admin)->get(route('queue-monitoring.data'))->assertJsonPath('stats.nurse_waiting', 0)
            ->assertJsonPath('stats.nurse_serving', 1);
        $this->actingAs($nurse);
        $this->post(route('nurse.station.assessment.complete', $ticket))->assertSessionHasErrors(['bite_site', 'animal_status', 'animal_vaccinated']);
        $this->assertSame('in_progress', $ticket->fresh()->status);
        $this->post(route('nurse.station.assessment.complete', $ticket), [
            'bite_site' => 'Right forearm', 'animal_status' => 'Unknown', 'animal_vaccinated' => '0',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'assessment_completed_by' => $nurse->id, 'status' => 'waiting',
        ]);
        $this->assertDatabaseHas('bite_incidents', [
            'patient_id' => $ticket->patient_id, 'bite_site' => 'Right forearm',
            'animal_status' => 'Unknown', 'animal_vaccinated' => 0,
        ]);
        $this->actingAs($admin)->get(route('queue-monitoring.data'))->assertJsonPath('stats.nurse_serving', 0)
            ->assertJsonPath('stats.doctor_waiting', 1);
        $this->actingAs($nurse);
        $this->post(route('nurse.station.assessment.complete', $ticket), [
            'bite_site' => 'Right forearm', 'animal_status' => 'Unknown', 'animal_vaccinated' => '0',
        ])->assertSessionHasErrors('assessment');

        $doctorStation = DoctorStation::where('name', 'Doctor 1')->firstOrFail();
        $this->actingAs($doctor)->post(route('doctor.stations.assign', $doctorStation))->assertRedirect(route('doctor.station.show'));
        $this->get(route('doctor.station.show'))->assertOk()
            ->assertSee('Doctor 1 — My Queue')->assertSee('My Queue')->assertSee('Doctor Waiting')->assertSee($ticket->ticket_number)
            ->assertSee('Nurse Assessment')->assertSee('Completed');
        $this->get(route('doctor.station.data'))->assertOk()->assertJsonPath('waiting_count', 1)
            ->assertJsonPath('waiting.0.ticket_number', $ticket->ticket_number);

        $this->post(route('doctor.station.call-next'))->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'status' => 'in_progress', 'assigned_staff_id' => $doctor->id,
            'counter_assigned' => $doctorStation->id,
        ]);
        $this->get(route('doctor.station.data'))->assertJsonPath('waiting_count', 0)
            ->assertJsonPath('current_ticket_id', $ticket->id)
            ->assertJsonPath('current_status', 'in_progress');
        $this->actingAs($admin)->get(route('queue-monitoring.data'))->assertJsonPath('stats.doctor_waiting', 0)
            ->assertJsonPath('stats.doctor_serving', 1);
        $this->actingAs($doctor);
        $this->get(route('doctor.station.show'))->assertOk()
            ->assertSee('Now Serving')->assertSee('Nurse Completed')->assertSee('Right forearm')->assertSee('Animal status')->assertSee('Ready for Review')
            ->assertSee('In Consultation')->assertSee('Complete Vaccination Plan');

        $this->post(route('doctor.station.complete', $ticket))->assertSessionHasErrors('consultation');
        $this->assertSame('in_progress', $ticket->fresh()->status);

        $patient = $ticket->patient;
        $this->get(route('patients.plan', $patient))->assertOk()->assertSee('Exposure Category')->assertSee('Vaccine Type');
        $this->put(route('patients.plan.store', $patient), [
            'exposure_category' => 'II', 'schedule' => 'days_0_3_7', 'vaccine_type' => 'PVRV', 'planned_dose_count' => 3,
        ])->assertRedirect(route('patients.show', $patient));

        $this->post(route('doctor.station.consultation.draft', $ticket), [
            'clinical_narrative' => 'Documented exposure history and assessment findings.',
            'exposure_category' => 'II',
            'vaccine_type' => 'PVRV', 'schedule_code' => 'days_0_3_7',
            'planned_dose_count' => 3, 'vaccination_schedule' => 'Day 0, Day 3, Day 7',
            'additional_instructions' => 'Return for scheduled doses.', 'disposition' => 'complete_visit',
            'disposition_remarks' => 'No further on-site service today.',
        ])->assertRedirect()->assertSessionHas('status');
        $this->post(route('doctor.station.consultation.review', $ticket), [
            'clinical_narrative' => 'Documented exposure history and assessment findings.',
            'exposure_category' => 'II',
            'vaccine_type' => 'PVRV', 'schedule_code' => 'days_0_3_7',
            'planned_dose_count' => 3, 'vaccination_schedule' => 'Day 0, Day 3, Day 7',
            'additional_instructions' => 'Return for scheduled doses.', 'disposition' => 'complete_visit',
            'disposition_remarks' => 'No further on-site service today.',
        ])->assertRedirect(route('doctor.station.consultation.review-page', $ticket));
        $this->get(route('doctor.station.consultation.review-page', $ticket))->assertOk()->assertSee('Review Consultation');
        $this->post(route('doctor.station.consultation.finalize', $ticket), ['confirmed' => '1'])
            ->assertRedirect(route('doctor.station.show'))->assertSessionHas('status');
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'status' => 'completed', 'assigned_staff_id' => $doctor->id,
        ]);
        $this->assertDatabaseHas('consultations', [
            'ticket_id' => $ticket->id, 'doctor_id' => $doctor->id, 'status' => 'completed',
            'clinical_narrative' => 'Documented exposure history and assessment findings.',
        ]);
        $this->assertSame(3, VaccinationSchedule::where('patient_id', $patient->id)->where('status', 'scheduled')->count());
        $this->assertSame(0, VaccinationSchedule::where('patient_id', $patient->id)->where('status', 'administered')->count());
        $this->assertSame(1, Ticket::where('patient_id', $patient->id)->count(), 'The original ticket is reused through nurse transfer and consultation.');
        $this->post(route('doctor.station.complete', $ticket))->assertSessionHasErrors('consultation');
        $this->get(route('doctor.station.show'))->assertOk()->assertSee('Patients Consulted Today')->assertSee('No patients are currently waiting for a doctor.');
        $this->actingAs($admin)->get(route('queue-monitoring.data'))->assertJsonPath('stats.doctor_serving', 0)
            ->assertJsonPath('stats.doctor_waiting', 0);
        $this->actingAs($doctor)->get(route('doctor.dashboard.data'))->assertOk()
            ->assertJsonPath('stats.my_patients_today', 1)
            ->assertJsonPath('stats.consultations_completed', 1)
            ->assertJsonPath('stats.doctor_waiting', 0)
            ->assertJsonPath('stats.currently_serving', 0)
            ->assertJsonPath('activity.total', 1);
    }

    public function test_two_doctors_cannot_call_the_same_patient_and_doctor_cannot_open_an_unassigned_station(): void
    {
        $doctor1 = User::factory()->create(['role' => 'Doctor']);
        $doctor2 = User::factory()->create(['role' => 'Doctor']);
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $ticket = $this->ticket($this->patient('Taylor'), 'CALL-001', [
            'assessment_completed_by' => $nurse->id,
            'assessment_completed_at' => now(),
        ]);

        $station1 = DoctorStation::where('name', 'Doctor 1')->firstOrFail();
        $station2 = DoctorStation::where('name', 'Doctor 2')->firstOrFail();
        $this->actingAs($doctor1)->get(route('doctor.station.show'))->assertRedirect(route('dashboard'));
        $this->post(route('doctor.stations.assign', $station1))->assertRedirect(route('doctor.station.show'));
        $this->actingAs($doctor2)->post(route('doctor.stations.assign', $station2))->assertRedirect(route('doctor.station.show'));

        $this->actingAs($doctor1)->post(route('doctor.station.call-next'))->assertRedirect()->assertSessionHas('status');
        $this->actingAs($doctor2)->post(route('doctor.station.call-next'))->assertSessionHasErrors('consultation');
        $this->assertSame(1, Ticket::where('patient_id', $ticket->patient_id)->whereIn('status', ['called', 'in_progress'])->count());
        $this->assertSame($doctor1->id, $ticket->fresh()->assigned_staff_id);
    }

    public function test_existing_queue_actions_use_the_assigned_doctor_station_and_require_nurse_assessment(): void
    {
        $doctor = User::factory()->create(['role' => 'Doctor']);
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $ticket = $this->ticket($this->patient('Queue'), 'QUEUE-001');
        $doctorStation = DoctorStation::where('name', 'Doctor 1')->firstOrFail();
        $nurseStation = NurseStation::where('name', 'Nurse 1')->firstOrFail();

        $this->actingAs($doctor)->post(route('queue.call-next'))->assertRedirect()->assertSessionHas('status');
        $this->actingAs($doctor)->post(route('doctor.stations.assign', $doctorStation))->assertRedirect();
        $this->post(route('queue.call-next'))->assertRedirect()->assertSessionHas('status');
        $this->assertSame('waiting', $ticket->fresh()->status, 'A doctor cannot bypass the nurse assessment marker.');

        $this->actingAs($nurse)->post(route('nurse.stations.assign', $nurseStation))->assertRedirect();
        $this->post(route('nurse.station.call-next'))->assertRedirect();
        $this->post(route('nurse.station.assessment.complete', $ticket), [
            'bite_site' => 'Left leg', 'animal_status' => 'Healthy', 'animal_vaccinated' => '1',
        ])->assertRedirect();
        $this->actingAs($doctor)->post(route('queue.call-next'), ['counter' => 4])->assertRedirect();
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'status' => 'called', 'assigned_staff_id' => $doctor->id,
            'doctor_station_id' => $doctorStation->id, 'counter_assigned' => $doctorStation->id,
        ]);
        $this->post(route('queue.serve', $ticket))->assertRedirect();
        $this->assertSame('in_progress', $ticket->fresh()->status);
    }

    public function test_nurse_and_staff_cannot_use_doctor_station_actions(): void
    {
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $staff = User::factory()->create(['role' => 'Staff']);
        $ticket = $this->ticket($this->patient('Jordan'), 'ACCESS-001');

        $this->actingAs($nurse)->get(route('doctor.station.show'))->assertForbidden();
        $this->get(route('doctor.station.data'))->assertForbidden();
        $this->post(route('doctor.station.call-next'))->assertForbidden();
        $this->actingAs($staff)->post(route('doctor.station.complete', $ticket))->assertForbidden();
        $this->assertSame('waiting', $ticket->fresh()->status);
    }

    private function patient(string $firstName): Patient
    {
        $patient = Patient::create([
            'first_name' => $firstName, 'last_name' => 'Workflow', 'dob' => '1990-01-01',
            'sex' => 'Female', 'address' => '1 Test Road', 'barangay' => 'Barangka',
        ]);
        BiteIncident::create([
            'patient_id' => $patient->id, 'date_of_bite' => now()->toDateString(),
            'animal_type' => 'Dog', 'exposure_category' => 'II',
        ]);

        return $patient;
    }

    private function ticket(Patient $patient, string $number, array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'patient_id' => $patient->id, 'ticket_number' => $number, 'status' => 'waiting',
            'is_priority' => false, 'visit_type' => 'new', 'service_stage' => 'assessment',
        ], $overrides));
    }
}
