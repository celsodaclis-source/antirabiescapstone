<?php

namespace Tests\Feature;

use App\Models\BiteIncident;
use App\Models\NurseStation;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VaccineInventory;
use App\Models\VaccinationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NurseVaccineAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_nurse_can_administer_only_due_ordered_dose_at_active_station_and_stock_is_deducted_once(): void
    {
        config()->set('vaccination.administration_sites', ['Left deltoid']);
        config()->set('vaccination.administration_routes.PVRV', 'clinic-approved route');
        $admin = User::factory()->create(['role' => 'Admin']);
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $patient = $this->patientWithOrder();
        $schedule = $patient->vaccinationSchedules()->where('dose_number', 1)->firstOrFail();
        $ticket = $this->followUpTicket($patient, $schedule, $nurse);
        $batch = VaccineInventory::create([
            'batch_number' => 'NURSE-VAX-01', 'vaccine_name' => 'PVRV', 'quantity' => 2,
            'expiration_date' => today()->addYear()->toDateString(), 'low_stock_threshold' => 1,
        ]);

        $this->actingAs($nurse)->get(route('nurse.station.show'))->assertOk()->assertSee('Administer Vaccine');
        $this->actingAs($admin)->get(route('queue-monitoring.data'))->assertJsonPath('stats.nurse_serving', 1)
            ->assertJsonPath('stats.doctor_waiting', 0);
        $this->actingAs($nurse);
        $this->get(route('nurse.station.vaccine.form', $ticket))->assertOk()
            ->assertSee('Vaccine Administration')->assertSee('Patient #'.$patient->id)
            ->assertSee('PVRV')->assertSee('Dose 1 of 3')->assertSee('NURSE-VAX-01');
        $this->post(route('nurse.station.vaccine.administer', $ticket), [
            'inventory_id' => $batch->id,
            'administration_date' => today()->toDateString(),
            'administration_site' => 'Left deltoid',
            'administration_remarks' => 'Observed after administration; no immediate reaction reported.',
        ])->assertRedirect(route('nurse.station.show'))->assertSessionHas('status');

        $this->assertDatabaseHas('vaccination_schedules', [
            'id' => $schedule->id, 'status' => 'administered', 'administered_by' => $nurse->id,
            'inventory_id' => $batch->id, 'administration_site' => 'Left deltoid',
            'administration_route' => 'clinic-approved route',
            'administration_remarks' => 'Observed after administration; no immediate reaction reported.',
        ]);
        $this->assertSame(1, $batch->fresh()->quantity);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'completed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'nurse.vaccine_administer', 'user_id' => $nurse->id]);
        $this->actingAs($admin)->get(route('queue-monitoring.data'))->assertJsonPath('stats.nurse_serving', 0)
            ->assertJsonPath('stats.doctor_waiting', 0)->assertJsonPath('stats.nurse_waiting', 0);
        $this->actingAs($nurse);
        $this->post(route('nurse.station.vaccine.administer', $ticket), [
            'inventory_id' => $batch->id, 'administration_date' => today()->toDateString(),
            'administration_site' => 'Left deltoid', 'administration_remarks' => 'Duplicate attempt.',
        ])->assertSessionHasErrors('ticket');
        $this->assertSame(1, $batch->fresh()->quantity);
    }

    public function test_nurse_cannot_administer_without_approved_site_configuration_or_due_order(): void
    {
        config()->set('vaccination.administration_sites', []);
        config()->set('vaccination.administration_routes.PVRV', 'clinic-approved route');
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $patient = $this->patientWithOrder();
        $schedule = $patient->vaccinationSchedules()->where('dose_number', 1)->firstOrFail();
        $ticket = $this->followUpTicket($patient, $schedule, $nurse);
        $this->actingAs($nurse)->get(route('nurse.station.vaccine.form', $ticket))
            ->assertOk()->assertSee('not configured approved administration sites');
        $this->post(route('nurse.station.vaccine.administer', $ticket), [
            'inventory_id' => 1, 'administration_date' => today()->toDateString(),
            'administration_site' => 'invented site', 'administration_remarks' => 'Test',
        ])->assertSessionHasErrors('administration_site');
        $this->assertSame('in_progress', $ticket->fresh()->status);
        $this->assertSame('scheduled', $schedule->fresh()->status);
    }

    public function test_configured_due_follow_up_ticket_is_counted_only_in_nurse_waiting(): void
    {
        config()->set('vaccination.administration_sites', ['Left deltoid']);
        config()->set('vaccination.administration_routes.PVRV', 'clinic-approved route');
        VaccineInventory::create([
            'batch_number' => 'QUEUE-VAX-01', 'vaccine_name' => 'PVRV', 'quantity' => 1,
            'expiration_date' => today()->addYear()->toDateString(), 'low_stock_threshold' => 1,
        ]);
        $nurse = User::factory()->create(['role' => 'Nurse']);
        $admin = User::factory()->create(['role' => 'Admin']);
        $patient = $this->patientWithOrder();
        $schedule = $patient->vaccinationSchedules()->where('dose_number', 1)->firstOrFail();
        $ticket = $this->followUpTicket($patient, $schedule, $nurse);
        $ticket->update(['status' => 'waiting', 'assigned_staff_id' => null, 'counter_assigned' => null]);

        $this->actingAs($admin)->get(route('queue-monitoring.data'))->assertJsonPath('stats.nurse_waiting', 1)
            ->assertJsonPath('stats.doctor_waiting', 0);
    }

    public function test_non_nurse_cannot_administer_from_nurse_station(): void
    {
        $doctor = User::factory()->create(['role' => 'Doctor']);
        $patient = $this->patientWithOrder();
        $ticket = Ticket::create([
            'patient_id' => $patient->id, 'ticket_number' => 'A-902', 'status' => 'waiting',
            'visit_type' => 'returning', 'service_stage' => 'follow_up',
            'vaccination_schedule_id' => $patient->vaccinationSchedules()->first()->id,
        ]);
        $this->actingAs($doctor)->get(route('nurse.station.vaccine.form', $ticket))->assertForbidden();
    }

    private function patientWithOrder(): Patient
    {
        $patient = Patient::create([
            'first_name' => 'Follow', 'last_name' => 'Up', 'dob' => '1990-01-01', 'sex' => 'Female',
            'address' => 'Test Road', 'barangay' => 'Barangka', 'vaccine_type' => 'PVRV', 'planned_dose_count' => 3,
        ]);
        BiteIncident::create([
            'patient_id' => $patient->id, 'date_of_bite' => today()->subDays(7)->toDateString(),
            'animal_type' => 'Dog', 'exposure_category' => 'II',
        ]);
        foreach ([0, 3, 7] as $index => $day) {
            VaccinationSchedule::create([
                'patient_id' => $patient->id, 'dose_number' => $index + 1,
                'scheduled_date' => today()->subDays(7)->addDays($day)->toDateString(),
                'status' => $index === 0 ? 'scheduled' : 'administered',
            ]);
        }

        return $patient;
    }

    private function followUpTicket(Patient $patient, VaccinationSchedule $schedule, User $nurse): Ticket
    {
        $station = NurseStation::where('name', 'Nurse 1')->firstOrFail();
        $station->update(['user_id' => $nurse->id]);

        return Ticket::create([
            'patient_id' => $patient->id, 'vaccination_schedule_id' => $schedule->id,
            'ticket_number' => 'A-901', 'status' => 'in_progress', 'visit_type' => 'returning',
            'service_stage' => 'follow_up', 'assigned_staff_id' => $nurse->id, 'counter_assigned' => $station->id,
        ]);
    }
}
