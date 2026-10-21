<?php

namespace Tests\Feature;

use App\Models\BiteIncident;
use App\Models\DoctorStation;
use App\Models\NurseStation;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_database_backed_stage_counts_and_station_tickets(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $doctor = User::factory()->create(['role' => 'Doctor']);
        $nurseStation = NurseStation::where('name', 'Nurse 1')->firstOrFail();
        $doctorStation = DoctorStation::where('name', 'Doctor 2')->firstOrFail();
        $nurseStation->update(['user_id' => $nurse->id]);
        $doctorStation->update(['user_id' => $doctor->id]);

        $nurseWaiting = $this->ticket('NURSE-WAIT', 'waiting', 'assessment');
        $doctorWaiting = $this->ticket('DOCTOR-WAIT', 'waiting', 'assessment', [
            'assessment_completed_by' => $nurse->id,
            'assessment_completed_at' => now(),
        ]);
        $nurseServing = $this->ticket('NURSE-SERVE', 'in_progress', 'assessment', [
            'assigned_staff_id' => $nurse->id,
            'counter_assigned' => $nurseStation->id,
        ]);
        $doctorServing = $this->ticket('DOCTOR-SERVE', 'in_progress', 'assessment', [
            'assigned_staff_id' => $doctor->id,
            'doctor_station_id' => $doctorStation->id,
            'counter_assigned' => $doctorStation->id,
            'assessment_completed_by' => $nurse->id,
            'assessment_completed_at' => now(),
        ]);
        $held = $this->ticket('HELD-001', 'held', 'assessment');
        $served = $this->ticket('SERVED-001', 'completed', 'assessment', ['completed_at' => now()]);

        $this->actingAs($admin)->get(route('queue-monitoring.index'))->assertOk()
            ->assertSee('Queue Monitoring')->assertSee('NURSE-SERVE')->assertSee('DOCTOR-SERVE')
            ->assertSee('Now Serving')->assertSee('Waiting')->assertSee('On Hold')->assertSee('Served Today');

        $this->get(route('queue-monitoring.data'))->assertOk()->assertJsonPath('stats.nurse_waiting', 1)
            ->assertJsonPath('stats.doctor_waiting', 1)
            ->assertJsonPath('stats.nurse_serving', 1)
            ->assertJsonPath('stats.doctor_serving', 1)
            ->assertJsonPath('stats.on_hold', 1)
            ->assertJsonPath('stats.served_today', 1)
            ->assertJsonPath('nurse_stations.0.ticket', $nurseServing->ticket_number)
            ->assertJsonPath('doctor_stations.1.ticket', $doctorServing->ticket_number)
            ->assertJsonPath('now_serving.0.ticket_number', $nurseServing->ticket_number)
            ->assertJsonPath('stats.now_serving', 2)
            ->assertJsonPath('nurse_waiting.0.ticket_number', $nurseWaiting->ticket_number)
            ->assertJsonPath('doctor_waiting.0.ticket_number', $doctorWaiting->ticket_number)
            ->assertJsonPath('held.0.ticket_number', $held->ticket_number)
            ->assertJsonPath('served_today.0.ticket_number', $served->ticket_number)
            ->assertJsonPath('nurse_stations.1.status', 'Idle');

        $this->assertSame('waiting', $nurseWaiting->fresh()->status);
        $this->assertSame('waiting', $doctorWaiting->fresh()->status);
    }

    public function test_only_admin_can_view_monitoring_page_or_refresh_endpoint(): void
    {
        foreach (['Doctor', 'Nurse', 'Staff'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('queue-monitoring.index'))->assertForbidden();
            $this->get(route('queue-monitoring.data'))->assertForbidden();
        }
    }

    public function test_nurse_station_calls_and_assessment_update_the_shared_monitoring_queue(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $station = NurseStation::where('name', 'Nurse 1')->firstOrFail();
        $ticket = $this->ticket('NURSE-FLOW', 'waiting', 'assessment');
        $this->actingAs($nurse)->post(route('nurse.stations.assign', $station))->assertRedirect();

        $this->actingAs($admin)->get(route('queue-monitoring.data'))->assertJsonPath('stats.nurse_waiting', 1)
            ->assertJsonPath('stats.nurse_serving', 0);
        $this->actingAs($nurse)->post(route('nurse.station.call-next'))->assertSessionHas('status');
        $this->actingAs($admin)->get(route('queue-monitoring.data'))->assertJsonPath('stats.nurse_waiting', 0)
            ->assertJsonPath('stats.nurse_serving', 1)
            ->assertJsonPath('nurse_stations.0.ticket', $ticket->ticket_number);

        $this->actingAs($nurse)->post(route('nurse.station.assessment.complete', $ticket), [
            'bite_site' => 'Left leg', 'animal_status' => 'Healthy', 'animal_vaccinated' => '1',
        ])->assertSessionHas('status');
        $this->actingAs($admin)->get(route('queue-monitoring.data'))->assertJsonPath('stats.nurse_serving', 0)
            ->assertJsonPath('stats.doctor_waiting', 1)
            ->assertJsonPath('nurse_stations.0.status', 'Idle');
    }

    private function ticket(string $number, string $status, string $stage, array $overrides = []): Ticket
    {
        $patient = Patient::create([
            'first_name' => 'Queue', 'last_name' => 'Monitor', 'dob' => '1990-01-01',
            'sex' => 'Female', 'address' => 'Test Road', 'barangay' => 'Barangka',
        ]);
        BiteIncident::create([
            'patient_id' => $patient->id, 'date_of_bite' => now()->toDateString(), 'animal_type' => 'Dog',
        ]);

        return Ticket::create(array_merge([
            'patient_id' => $patient->id,
            'ticket_number' => $number,
            'status' => $status,
            'service_stage' => $stage,
            'visit_type' => 'new',
        ], $overrides));
    }
}
