<?php

namespace Tests\Feature;

use App\Models\NurseStation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NurseStationAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_nurse_can_assign_and_release_a_station(): void
    {
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $station = NurseStation::where('name', 'Nurse 1')->firstOrFail();

        $this->actingAs($nurse)->get(route('dashboard'))
            ->assertOk()->assertSee('Nurse Dashboard')->assertSee('Off Duty')->assertSee('No nurse assigned.');

        $this->post(route('nurse.stations.assign', $station))
            ->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('nurse_stations', ['id' => $station->id, 'user_id' => $nurse->id]);
        $this->get(route('dashboard'))->assertOk()->assertSee('On Duty')->assertSee('Occupied')->assertSee($nurse->name);

        $this->delete(route('nurse.stations.release'))->assertRedirect();
        $this->assertDatabaseHas('nurse_stations', ['id' => $station->id, 'user_id' => null]);
        $this->get(route('dashboard'))->assertOk()->assertSee('Off Duty')->assertSee('Available');
    }

    public function test_station_and_nurse_cannot_be_assigned_twice(): void
    {
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $otherNurse = User::factory()->create(['role' => 'Nurse']);
        $first = NurseStation::where('name', 'Nurse 1')->firstOrFail();
        $second = NurseStation::where('name', 'Nurse 2')->firstOrFail();

        $this->actingAs($nurse)->post(route('nurse.stations.assign', $first))->assertRedirect();
        $this->actingAs($otherNurse)->post(route('nurse.stations.assign', $first))->assertSessionHasErrors('station');
        $this->actingAs($nurse)->post(route('nurse.stations.assign', $second))->assertSessionHasErrors('station');

        $this->assertSame(1, NurseStation::where('user_id', $nurse->id)->count());
        $this->assertNull($second->fresh()->user_id);
    }

    public function test_only_nurses_can_view_or_change_station_assignments(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $station = NurseStation::where('name', 'Nurse 1')->firstOrFail();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertDontSee('Nurse Dashboard');
        $this->post(route('nurse.stations.assign', $station))->assertForbidden();
        $this->delete(route('nurse.stations.release'))->assertForbidden();
    }
}
