<?php

namespace Tests\Feature;

use App\Models\DoctorStation;
use App\Models\BiteIncident;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_login_lands_on_the_doctor_dashboard(): void
    {
        $doctor = User::factory()->create(['role' => 'Doctor']);

        $this->post(route('login'), ['email' => $doctor->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('Doctor Dashboard');
    }

    public function test_doctor_dashboard_shows_four_database_backed_stations_and_duty_status(): void
    {
        $doctor = User::factory()->create(['role' => 'Doctor']);
        $otherDoctor = User::factory()->create(['role' => 'Doctor']);

        $station = DoctorStation::where('name', 'Doctor 1')->firstOrFail();
        $response = $this->actingAs($doctor)->get(route('dashboard'))
            ->assertOk()->assertSee('Doctor Dashboard')->assertSee('Select your station to begin patient consultations.')
            ->assertSee('Doctor 1')->assertSee('Doctor 2')->assertSee('Doctor 3')->assertSee('Doctor 4')
            ->assertSee('Off Duty')->assertSee('No doctor assigned')->assertSee('Available')->assertSee('Open Station')
            ->assertSee('My Patients Today')->assertSee('Consultations Completed')->assertSee('Doctor Waiting')
            ->assertSee('Currently Serving')->assertSee("Today's Consultation Activity", false);
        foreach (['my_patients_today', 'consultations_completed', 'doctor_waiting', 'currently_serving'] as $key) {
            $this->assertSame(1, substr_count($response->getContent(), 'id="doctor-stat-'.$key.'"'));
        }
        $this->assertSame(4, substr_count($response->getContent(), 'id="doctor-stat-'));
        $this->assertSame(4, substr_count($response->getContent(), '<article class="rounded-2xl'));

        $this->actingAs($doctor)->post(route('doctor.stations.assign', $station))
            ->assertRedirect(route('doctor.station.show'))->assertSessionHas('status');
        $this->assertDatabaseHas('doctor_stations', ['id' => $station->id, 'user_id' => $doctor->id]);
        $this->actingAs($otherDoctor)->get(route('dashboard'))->assertOk()
            ->assertSee('Assigned to another doctor')->assertSee('In Use');
        $this->actingAs($doctor);
        $this->get(route('dashboard'))->assertOk()->assertSee('On Duty')->assertSee('Open Doctor Station')->assertSee('Doctor 1');
    }

    public function test_doctor_station_cannot_be_shared_or_duplicated_and_roles_are_enforced(): void
    {
        $doctor = User::factory()->create(['role' => 'Doctor']);
        $anotherDoctor = User::factory()->create(['role' => 'Doctor']);
        $station1 = DoctorStation::where('name', 'Doctor 1')->firstOrFail();
        $station2 = DoctorStation::where('name', 'Doctor 2')->firstOrFail();

        $this->actingAs($doctor)->post(route('doctor.stations.assign', $station1))->assertRedirect();
        $this->actingAs($anotherDoctor)->post(route('doctor.stations.assign', $station1))->assertSessionHasErrors('station');
        $this->actingAs($doctor)->post(route('doctor.stations.assign', $station2))->assertSessionHasErrors('station');
        $this->assertSame(1, DoctorStation::where('user_id', $doctor->id)->count());
        $this->assertNull($station2->fresh()->user_id);

        $this->actingAs($doctor)->delete(route('doctor.stations.release'))->assertRedirect();
        $this->assertNull($station1->fresh()->user_id);

        $nurse = User::factory()->create(['role' => 'Nurse']);
        $this->actingAs($nurse)->get(route('dashboard'))->assertOk()->assertDontSee('Doctor Stations');
        $this->post(route('doctor.stations.assign', $station1))->assertForbidden();
        $this->delete(route('doctor.stations.release'))->assertForbidden();
    }

    public function test_dashboard_kpis_use_individual_and_shared_scopes_and_activity_rows(): void
    {
        $doctor = User::factory()->create(['role' => 'Doctor']);
        $otherDoctor = User::factory()->create(['role' => 'Doctor']);
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $station = DoctorStation::where('name', 'Doctor 2')->firstOrFail();
        $station->update(['user_id' => $doctor->id]);

        $completedPatient = $this->patient('Completed');
        $completedOne = $this->ticket($completedPatient, 'TODAY-001', $doctor, $station, [
            'status' => 'completed', 'called_at' => now()->subHour(), 'completed_at' => now()->subMinutes(30),
        ]);
        $this->ticket($completedPatient, 'TODAY-002', $doctor, $station, [
            'status' => 'completed', 'called_at' => now()->subMinutes(20), 'completed_at' => now()->subMinutes(10),
        ]);

        $servingPatient = $this->patient('Serving');
        $serving = $this->ticket($servingPatient, 'TODAY-003', $doctor, $station, [
            'status' => 'in_progress', 'called_at' => now()->subMinutes(5), 'served_at' => now()->subMinutes(5),
        ]);

        $doctorWaitingPatient = $this->patient('Waiting');
        $doctorWaiting = $this->ticket($doctorWaitingPatient, 'TODAY-004', null, null, [
            'status' => 'waiting', 'assessment_completed_by' => $nurse->id, 'assessment_completed_at' => now(),
        ]);
        $this->ticket($doctorWaitingPatient, 'TODAY-005', null, null, [
            'status' => 'waiting', 'assessment_completed_by' => $nurse->id, 'assessment_completed_at' => now(),
        ]);

        $otherPatient = $this->patient('Other');
        $this->ticket($otherPatient, 'OTHER-001', $otherDoctor, DoctorStation::where('name', 'Doctor 1')->firstOrFail(), [
            'status' => 'completed', 'called_at' => now(), 'completed_at' => now(),
        ]);

        $oldPatient = $this->patient('Yesterday');
        $this->ticket($oldPatient, 'YESTERDAY-001', $doctor, $station, [
            'status' => 'completed', 'called_at' => now()->subDay(), 'completed_at' => now()->subDay(),
        ]);

        $this->actingAs($doctor)->get(route('dashboard'))->assertOk()
            ->assertSee('TODAY-001')->assertSee('TODAY-003')->assertDontSee('OTHER-001');
        $data = $this->get(route('doctor.dashboard.data'))->assertOk()
            ->assertJsonPath('stats.my_patients_today', 2)
            ->assertJsonPath('stats.consultations_completed', 2)
            ->assertJsonPath('stats.doctor_waiting', 1)
            ->assertJsonPath('stats.currently_serving', 1)
            ->assertJsonPath('activity.total', 3)
            ->assertJsonPath('activity.data.0.ticket_number', $serving->ticket_number)
            ->assertJsonPath('activity.data.0.station_name', 'Doctor 2');

        $this->assertSame('completed', $completedOne->fresh()->status);
        $this->assertSame('waiting', $doctorWaiting->fresh()->status);
    }

    public function test_non_doctor_cannot_read_doctor_dashboard_data_endpoint(): void
    {
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $this->actingAs($nurse)->get(route('doctor.dashboard.data'))->assertForbidden();
    }

    private function patient(string $firstName): Patient
    {
        $patient = Patient::create([
            'first_name' => $firstName, 'last_name' => 'Dashboard', 'dob' => '1990-01-01',
            'sex' => 'Female', 'address' => 'Test Road', 'barangay' => 'Barangka',
        ]);
        BiteIncident::create(['patient_id' => $patient->id, 'date_of_bite' => now()->toDateString(), 'animal_type' => 'Dog']);

        return $patient;
    }

    private function ticket(Patient $patient, string $number, ?User $doctor, ?DoctorStation $station, array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'patient_id' => $patient->id,
            'ticket_number' => $number,
            'status' => 'completed',
            'service_stage' => 'assessment',
            'visit_type' => 'new',
            'assigned_staff_id' => $doctor?->id,
            'doctor_station_id' => $station?->id,
            'counter_assigned' => $station?->id,
            'called_at' => now(),
            'completed_at' => now(),
        ], $overrides));
    }

}
