<?php

namespace Tests\Feature;

use App\Models\BiteIncident;
use App\Models\DoctorStation;
use App\Models\NurseStation;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\User;
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
        $ticket = $this->ticket($this->patient('Alex'), 'ASSESS-001');

        $nurseStation = NurseStation::where('name', 'Nurse 1')->firstOrFail();
        $this->actingAs($nurse)->post(route('nurse.stations.assign', $nurseStation))->assertRedirect();
        $this->get(route('nurse.station.show'))->assertOk()->assertSee('Waiting for Nurse Assessment')->assertSee('ASSESS-001');
        $this->post(route('nurse.station.assessment.complete', $ticket))->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'assessment_completed_by' => $nurse->id,
        ]);

        $doctorStation = DoctorStation::where('name', 'Doctor 1')->firstOrFail();
        $this->actingAs($doctor)->post(route('doctor.stations.assign', $doctorStation))->assertRedirect(route('doctor.station.show'));
        $this->get(route('doctor.station.show'))->assertOk()
            ->assertSee('Doctor Station 1')->assertSee('Doctor Waiting')->assertSee('ASSESS-001')
            ->assertSee('Nurse Assessment')->assertSee('Completed');

        $this->post(route('doctor.station.call-next'))->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'status' => 'in_progress', 'assigned_staff_id' => $doctor->id,
            'counter_assigned' => $doctorStation->id,
        ]);
        $this->get(route('doctor.station.show'))->assertOk()
            ->assertSee('Now Serving')->assertSee('Nurse Completed')->assertSee('Ready for Review')
            ->assertSee('In Consultation')->assertSee('Complete Vaccination Plan');

        $this->post(route('doctor.station.complete', $ticket))->assertSessionHasErrors('consultation');
        $this->assertSame('in_progress', $ticket->fresh()->status);

        $patient = $ticket->patient;
        $patient->update(['vaccine_type' => 'PVRV', 'planned_dose_count' => 3]);
        $patient->biteIncidents()->firstOrFail()->update(['exposure_category' => 'II']);
        foreach ([0, 3, 7] as $index => $day) {
            VaccinationSchedule::create([
                'patient_id' => $patient->id, 'dose_number' => $index + 1,
                'scheduled_date' => now()->addDays($day)->toDateString(), 'status' => 'scheduled',
            ]);
        }

        $this->post(route('doctor.station.complete', $ticket))->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'status' => 'completed', 'assigned_staff_id' => $doctor->id,
        ]);
        $this->assertSame(3, VaccinationSchedule::where('patient_id', $patient->id)->where('status', 'scheduled')->count());
        $this->post(route('doctor.station.complete', $ticket))->assertSessionHasErrors('consultation');
        $this->get(route('doctor.station.show'))->assertOk()->assertSee('Patients Consulted Today')->assertSee('No patients are currently waiting for a doctor.');
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
        $this->post(route('nurse.station.assessment.complete', $ticket))->assertRedirect();
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
