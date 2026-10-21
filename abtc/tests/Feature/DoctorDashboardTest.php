<?php

namespace Tests\Feature;

use App\Models\DoctorStation;
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
            ->assertDontSee('My Patients Today')->assertDontSee('Today at a glance');
        $this->assertSame(4, substr_count($response->getContent(), '<article class="rounded-2xl'));

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

}
