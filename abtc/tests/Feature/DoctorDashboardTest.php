<?php

namespace Tests\Feature;

use App\Models\DoctorStation;
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

    public function test_doctor_dashboard_uses_doctors_queue_records_and_station_status(): void
    {
        $doctor = User::factory()->create(['role' => 'Doctor']);
        $otherDoctor = User::factory()->create(['role' => 'Doctor']);
        $todayPatient = $this->patient('Today');
        $activePatient = $this->patient('Active');
        $waitingPatient = $this->patient('Waiting');

        $this->ticket($todayPatient, 'completed', $doctor, now()->startOfDay()->addHour(), now()->startOfDay()->addHours(2));
        // A second handled ticket for the same patient must not double-count the patient.
        $this->ticket($todayPatient, 'completed', $doctor, now()->startOfDay()->addHours(3), now()->startOfDay()->addHours(4));
        $this->ticket($activePatient, 'in_progress', $doctor, now());
        $this->ticket($waitingPatient, 'waiting');
        $this->ticket($this->patient('Other Doctor'), 'completed', $otherDoctor, now(), now());

        $station = DoctorStation::where('name', 'Doctor 1')->firstOrFail();
        $this->actingAs($doctor)->get(route('dashboard'))
            ->assertOk()->assertSee('Doctor Dashboard')->assertSee('Daily consultations and station management')
            ->assertSee('My Patients Today')->assertSee('Consultations Completed')
            ->assertSee('Doctor Waiting')->assertSee('Currently Serving')
            ->assertSee('>1</p>', false)->assertSee('>2</p>', false)
            ->assertSee('Off Duty')->assertSee('Ready for consultation');

        $this->actingAs($doctor)->post(route('doctor.stations.assign', $station))
            ->assertRedirect(route('dashboard'))->assertSessionHas('status');
        $this->assertDatabaseHas('doctor_stations', ['id' => $station->id, 'user_id' => $doctor->id]);
        $this->actingAs($otherDoctor)->get(route('dashboard'))->assertOk()
            ->assertSee('Assigned to another doctor')->assertSee('In Use');
        $this->actingAs($doctor);
        $this->get(route('dashboard'))->assertOk()->assertSee('On Duty')->assertSee('Go to Patient Queue')->assertSee('Doctor 1');
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

    private function patient(string $firstName): Patient
    {
        return Patient::create([
            'first_name' => $firstName, 'last_name' => 'Patient', 'dob' => '1990-01-01',
            'sex' => 'Female', 'address' => '1 Test Road', 'barangay' => 'Barangka',
        ]);
    }

    private function ticket(Patient $patient, string $status, ?User $doctor = null, $calledAt = null, $completedAt = null): Ticket
    {
        return Ticket::create([
            'patient_id' => $patient->id,
            'ticket_number' => 'T-'.fake()->unique()->numerify('#####'),
            'status' => $status,
            'assigned_staff_id' => $doctor?->id,
            'called_at' => $calledAt,
            'completed_at' => $completedAt,
        ]);
    }
}
