<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use App\Services\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PatientWorkflowEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private User $nurse;
    private User $doctor;
    private VaccineInventory $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nurse = User::factory()->create(['role' => 'Nurse']);
        $this->doctor = User::factory()->create(['role' => 'Doctor']);
        $this->batch = VaccineInventory::create([
            'batch_number' => 'E2E-001', 'vaccine_name' => 'PVRV', 'quantity' => 5,
            'expiration_date' => now()->addYear()->toDateString(), 'low_stock_threshold' => 1,
        ]);
    }

    public function test_new_and_emergency_registration_are_routed_for_assessment(): void
    {
        $new = $this->registerPatient('New', 'regular');
        $this->assertSame('new', $new->visit_type);
        $this->assertSame('assessment', $new->service_stage);
        $this->assertSame('waiting', $new->status);
        $this->assertSame(0, $new->patient->vaccinationSchedules()->count());

        $this->recordPlan($new->patient, 4);
        $this->assertDatabaseCount('vaccination_schedules', 4);

        $emergency = $this->registerPatient('Emergency', 'emergency');
        $this->assertSame('medical_review', $emergency->service_stage);
        $this->assertTrue($emergency->is_priority);
    }

    public function test_call_hold_skip_recall_and_completion_follow_valid_queue_transitions(): void
    {
        $first = $this->registerPatient('First', 'regular');
        $second = $this->registerPatient('Second', 'regular');

        $this->actingAs($this->nurse)->post(route('queue.call-next'), ['counter' => 1])->assertRedirect();
        $first->refresh();
        $this->assertSame('called', $first->status);

        $this->actingAs($this->nurse)->post(route('queue.call-next'), ['counter' => 1])->assertRedirect();
        $this->assertSame('waiting', $second->fresh()->status);

        $this->actingAs($this->nurse)->post(route('queue.hold', $first))->assertRedirect();
        $this->assertSame('held', $first->fresh()->status);
        $this->assertNull($first->fresh()->counter_assigned);

        $this->actingAs($this->nurse)->post(route('queue.call-next'), ['counter' => 1])->assertRedirect();
        $this->assertSame('called', $second->fresh()->status);
        $this->actingAs($this->nurse)->post(route('queue.call', $first), ['counter' => 1])->assertRedirect();
        $this->assertSame('held', $first->fresh()->status, 'An occupied counter must reject a recall.');

        $this->actingAs($this->nurse)->post(route('queue.complete', $second))->assertRedirect();
        $this->assertSame('completed', $second->fresh()->status);
        $this->actingAs($this->nurse)->post(route('queue.call', $first), ['counter' => 1])->assertRedirect();
        $this->assertSame('called', $first->fresh()->status);
        $this->actingAs($this->nurse)->post(route('queue.serve', $first))->assertRedirect();
        $this->assertSame('in_progress', $first->fresh()->status);
        $this->actingAs($this->nurse)->post(route('queue.complete', $first))->assertRedirect();
        $this->assertSame('completed', $first->fresh()->status);
    }

    public function test_missed_return_dose_links_queue_history_and_inventory_administration(): void
    {
        $patient = Patient::create([
            'first_name' => 'Return', 'last_name' => 'Patient', 'dob' => '1990-01-01', 'sex' => 'Female',
            'address' => '1 Test Road', 'barangay' => 'Barangka', 'patient_type' => 'regular',
            'vaccine_type' => 'PVRV', 'planned_dose_count' => 4,
        ]);
        VaccinationSchedule::create([
            'patient_id' => $patient->id, 'dose_number' => 1,
            'scheduled_date' => now()->subDays(4)->toDateString(), 'status' => 'administered',
            'administered_at' => now()->subDays(4), 'administered_by' => $this->doctor->id,
            'inventory_id' => $this->batch->id,
        ]);
        $dose = VaccinationSchedule::create([
            'patient_id' => $patient->id, 'dose_number' => 2,
            'scheduled_date' => now()->subDay()->toDateString(), 'status' => 'missed',
        ]);

        $this->actingAs($this->nurse)->get(route('schedules.index', ['filter' => 'missed']))
            ->assertOk()->assertSee('Queue dose');
        $this->actingAs($this->nurse)->post(route('schedules.queue', $dose))->assertRedirect(route('queue.index'));
        $ticket = Ticket::where('vaccination_schedule_id', $dose->id)->firstOrFail();
        $this->assertSame('returning', $ticket->visit_type);
        $this->assertSame('follow_up', $ticket->service_stage);
        $this->assertSame('waiting', $ticket->status);
        $this->actingAs($this->nurse)->get(route('queue.index'))->assertOk()->assertSee('Dose 2');
        $this->actingAs($this->nurse)->post(route('schedules.queue', $dose))->assertRedirect();
        $this->assertSame(1, Ticket::where('vaccination_schedule_id', $dose->id)->count());

        $this->actingAs($this->nurse)->post(route('queue.call-next'))->assertRedirect();
        $this->actingAs($this->nurse)->post(route('queue.serve', $ticket))->assertRedirect();
        $this->actingAs($this->nurse)->post(route('queue.complete', $ticket))->assertRedirect();
        $this->assertSame('in_progress', $ticket->fresh()->status, 'Dose-linked tickets cannot be marked done before administration.');

        $stockBefore = $this->batch->quantity;
        $this->actingAs($this->doctor)->post(route('schedules.administer', $dose), [
            'vaccine_type' => 'Other Vaccine', 'inventory_id' => $this->batch->id,
        ])->assertSessionHasErrors('vaccine_type');
        $this->assertSame($stockBefore, $this->batch->fresh()->quantity);

        $this->actingAs($this->doctor)->post(route('schedules.administer', $dose), [
            'vaccine_type' => 'PVRV', 'inventory_id' => $this->batch->id,
        ])->assertRedirect(route('schedules.index'));

        $this->assertSame('administered', $dose->fresh()->status);
        $this->assertSame($stockBefore - 1, $this->batch->fresh()->quantity);
        $this->assertSame($this->batch->id, $dose->fresh()->inventory_id);
        $this->assertSame('completed', $ticket->fresh()->status);
        $this->assertNotNull($dose->fresh()->administered_at);
        $this->actingAs($this->nurse)->get(route('patients.show', $patient))->assertOk()->assertSee('E2E-001');
    }

    public function test_missed_dose_is_included_in_overdue_reminders_and_logged(): void
    {
        SmsGateway::saveSettings(['enabled' => true, 'host' => '10.0.0.5:8080', 'username' => 'sms', 'password' => 'secret']);
        Http::fake(['*' => Http::response(['state' => 'Pending'], 200)]);
        $patient = Patient::create([
            'first_name' => 'Missed', 'last_name' => 'Reminder', 'dob' => '1990-01-01', 'sex' => 'Male',
            'contact_number' => '09171234567', 'address' => '1 Test Road', 'barangay' => 'Barangka',
            'patient_type' => 'regular', 'vaccine_type' => 'PVRV', 'planned_dose_count' => 3,
        ]);
        $dose = VaccinationSchedule::create([
            'patient_id' => $patient->id, 'dose_number' => 2,
            'scheduled_date' => now()->subDays(2)->toDateString(), 'status' => 'missed',
        ]);

        $this->actingAs($this->nurse)->post(route('sms.send', $dose))->assertRedirect();
        $this->assertDatabaseHas('sms_logs', ['patient_id' => $patient->id, 'recipient' => $patient->contact_number]);
        Http::assertSentCount(1);
    }

    private function registerPatient(string $firstName, string $patientType): Ticket
    {
        $this->actingAs($this->nurse)->post(route('patients.store'), [
            'first_name' => $firstName, 'last_name' => 'Workflow', 'sex' => 'Male',
            'dob' => '1990-01-01', 'contact_number' => '09171234567', 'address' => '1 Test Road',
            'barangay' => 'Barangka', 'date_of_bite' => now()->toDateString(), 'animal_type' => 'Dog',
            'exposure_category' => 'II', 'register_queue' => '1', 'patient_type' => $patientType,
        ])->assertRedirect();

        return Ticket::latest('id')->firstOrFail();
    }

    private function recordPlan(Patient $patient, int $doseCount): void
    {
        $this->actingAs($this->doctor)->put(route('patients.plan.store', $patient), [
            'exposure_category' => 'II',
            'schedule' => $doseCount === 3 ? 'days_0_3_7' : 'days_0_3_7_28',
            'vaccine_type' => 'PVRV',
            'planned_dose_count' => $doseCount,
        ])->assertRedirect(route('patients.show', $patient));
    }
}
