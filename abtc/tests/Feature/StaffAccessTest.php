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

        // Staff only collect information: no plan is recorded and no doses are generated at registration.
        $this->assertNull($patient->vaccine_type);
        $this->assertNull($patient->planned_dose_count);
        $this->assertSame(0, $patient->vaccinationSchedules()->count());

        // A doctor or nurse records the plan later; until then no dose can be queued.
        $dueDose = VaccinationSchedule::create([
            'patient_id' => $patient->id, 'dose_number' => 1,
            'scheduled_date' => today(), 'status' => 'scheduled',
        ]);
        $this->post(route('staff.tickets.store'), ['schedule_id' => $dueDose->id])->assertSessionHasErrors('schedule_id');
        $this->assertSame(1, $patient->tickets()->whereIn('status', ['waiting', 'called', 'in_progress', 'held'])->count());
    }

    public function test_staff_registration_form_hides_clinical_sections_and_ignores_plan_fields(): void
    {
        $this->actingAs($this->staff)->get(route('staff.patients.create'))
            ->assertOk()
            ->assertDontSee('Vaccination Plan')
            ->assertDontSee('Route for medical review')
            ->assertDontSee('Exposure Category')
            ->assertDontSee('Generate Ticket')
            ->assertDontSee('Generate a queue ticket before registering this patient.');

        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin)->get(route('patients.create'))
            ->assertOk()
            ->assertSee('Exposure Category')
            ->assertSee('Generate Ticket')
            ->assertSee('Generate a queue ticket before registering this patient.');

        $nurse = User::factory()->create(['role' => 'Nurse']);
        $this->actingAs($nurse)->get(route('patients.create'))
            ->assertOk()
            ->assertSee('Vaccination Plan')
            ->assertSee('Route for medical review');

        $data = $this->registrationData('Casey');
        $data['vaccine_type'] = 'PVRV';
        $data['planned_dose_count'] = 4;
        $data['requires_medical_review'] = '1';
        $this->actingAs($this->staff)->post(route('staff.patients.store'), $data)->assertRedirect();

        $patient = Patient::where('first_name', 'Casey')->firstOrFail();
        $this->assertNull($patient->vaccine_type);
        $this->assertNull($patient->planned_dose_count);
        $this->assertSame(0, $patient->vaccinationSchedules()->count());
        $this->assertSame('assessment', $patient->tickets()->firstOrFail()->service_stage);
    }

    public function test_staff_can_register_patient_with_blank_exposure_category(): void
    {
        $data = $this->registrationData('Avery');
        unset($data['exposure_category']);

        $this->actingAs($this->staff)->post(route('staff.patients.store'), $data)->assertRedirect();

        $patient = Patient::where('first_name', 'Avery')->firstOrFail();
        $this->assertNull($patient->biteIncidents()->firstOrFail()->exposure_category);
        $this->assertSame(1, $patient->tickets()->count());
    }

    public function test_doctor_can_record_vaccination_plan_for_staff_registered_patient(): void
    {
        $data = $this->registrationData('Jordan');
        $this->actingAs($this->staff)->post(route('staff.patients.store'), $data)->assertRedirect();
        $patient = Patient::where('first_name', 'Jordan')->firstOrFail();

        $doctor = User::factory()->create(['role' => 'Doctor']);
        $staff = $this->staff;
        $this->actingAs($staff)->get(route('patients.plan', $patient))->assertForbidden();
        $this->actingAs($doctor)->get(route('patients.plan', $patient))->assertOk();

        $this->actingAs($doctor)->put(route('patients.plan.store', $patient), [
            'vaccine_type' => 'PVRV', 'planned_dose_count' => 4, 'requires_medical_review' => '1',
        ])->assertRedirect(route('patients.show', $patient));

        $patient->refresh();
        $this->assertSame('PVRV', $patient->vaccine_type);
        $this->assertSame(4, $patient->vaccinationSchedules()->count());
        $this->assertSame('medical_review', $patient->tickets()->firstOrFail()->service_stage);

        $this->actingAs($doctor)->get(route('patients.plan', $patient))->assertRedirect(route('patients.show', $patient));
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
            'patient_type' => 'regular',
        ];
    }
}
