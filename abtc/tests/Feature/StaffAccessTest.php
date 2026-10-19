<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => 'Staff']);
        VaccineInventory::create([
            'batch_number' => 'STAFF-001', 'vaccine_name' => 'PVRV', 'quantity' => 10,
            'expiration_date' => now()->addYear()->toDateString(), 'low_stock_threshold' => 1,
        ]);
    }

    public function test_staff_login_redirects_to_staff_dashboard_and_admin_dashboard_is_forbidden(): void
    {
        $this->post(route('login'), ['email' => $this->staff->email, 'password' => 'password'])
            ->assertRedirect(route('staff.dashboard'));

        $this->get(route('staff.dashboard'))->assertOk()->assertSee('Staff Dashboard');
        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_staff_can_register_patient_search_create_ticket_view_queue_and_print(): void
    {
        $data = $this->registrationData('Jamie');
        $this->actingAs($this->staff)->post(route('staff.patients.store'), $data)
            ->assertRedirect();

        $patient = Patient::where('first_name', 'Jamie')->firstOrFail();
        $newTicket = $patient->tickets()->firstOrFail();
        $this->assertSame('waiting', $newTicket->status);
        $this->assertSame('new', $newTicket->visit_type);
        $this->get(route('staff.tickets.print', $newTicket))->assertOk()->assertSee($newTicket->ticket_number);
        $this->get(route('staff.patients.search', ['search' => 'Jamie']))->assertOk()->assertSee('Jamie A Workflow');
        $this->get(route('staff.queue.index'))->assertOk()->assertSee($newTicket->ticket_number);

        $returningPatient = Patient::create([
            'first_name' => 'Taylor', 'last_name' => 'Returning', 'dob' => '1990-01-01', 'sex' => 'Female',
            'address' => '1 Test Road', 'barangay' => 'Barangka', 'patient_type' => 'regular',
            'vaccine_type' => 'PVRV', 'planned_dose_count' => 4,
        ]);
        $dose = VaccinationSchedule::create([
            'patient_id' => $returningPatient->id, 'dose_number' => 1,
            'scheduled_date' => today(), 'status' => 'scheduled',
        ]);

        $this->post(route('staff.tickets.store'), ['schedule_id' => $dose->id])->assertRedirect();
        $returningTicket = Ticket::where('vaccination_schedule_id', $dose->id)->firstOrFail();
        $this->assertSame('returning', $returningTicket->visit_type);
        $this->assertSame('follow_up', $returningTicket->service_stage);
        $this->assertSame('waiting', $returningTicket->status);
    }

    public function test_staff_registration_prevents_duplicate_patients_and_duplicate_active_tickets(): void
    {
        $data = $this->registrationData('Morgan');
        $this->actingAs($this->staff)->post(route('staff.patients.store'), $data)->assertRedirect();
        $patient = Patient::where('first_name', 'Morgan')->firstOrFail();
        $this->post(route('staff.patients.store'), $data)->assertSessionHasErrors('first_name');
        $this->assertSame(1, Patient::where('first_name', 'Morgan')->count());
        $this->assertSame(1, $patient->tickets()->count());

        $dueDose = $patient->vaccinationSchedules()->where('dose_number', 1)->firstOrFail();
        $this->post(route('staff.tickets.store'), ['schedule_id' => $dueDose->id])->assertSessionHasErrors('schedule_id');
        $this->assertSame(1, $patient->tickets()->whereIn('status', ['waiting', 'called', 'in_progress', 'held'])->count());
    }

    public function test_staff_cannot_access_admin_or_clinical_modules_and_can_log_out(): void
    {
        $this->actingAs($this->staff);
        foreach (['dashboard', 'patients.index', 'queue.index', 'inventory.index', 'reports.index', 'schedules.index', 'sms.index', 'files.index', 'settings.index', 'settings.users'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        $this->get(route('profile.edit'))->assertOk();

        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    private function registrationData(string $firstName): array
    {
        return [
            'first_name' => $firstName, 'middle_name' => 'A', 'last_name' => 'Workflow',
            'dob' => '1990-01-01', 'sex' => 'Female', 'contact_number' => '09171234567',
            'address' => '1 Test Road', 'barangay' => 'Barangka', 'date_of_bite' => today()->toDateString(),
            'animal_type' => 'Dog', 'exposure_category' => 'II', 'register_queue' => '1',
            'patient_type' => 'regular', 'vaccine_type' => 'PVRV', 'planned_dose_count' => 4,
        ];
    }
}
