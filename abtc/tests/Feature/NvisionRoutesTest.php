<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NvisionRoutesTest extends TestCase
{
    use RefreshDatabase;

    private VaccineInventory $batch;

    /** Create isolated staff and inventory fixtures for the feature tests. */
    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['name' => 'Test Admin', 'email' => 'admin@abtc.gov.ph', 'role' => 'Admin']);
        User::factory()->create(['name' => 'Test Nurse', 'email' => 'nurse@abtc.gov.ph', 'role' => 'Nurse']);
        User::factory()->create(['name' => 'Test Doctor', 'email' => 'doctor@abtc.gov.ph', 'role' => 'Doctor']);
        $this->batch = VaccineInventory::create([
            'batch_number' => 'ROUTE-001', 'vaccine_name' => 'PVRV', 'quantity' => 5,
            'expiration_date' => now()->addYear()->toDateString(), 'low_stock_threshold' => 1,
        ]);
    }

    public function test_all_module_routes_render_for_admin(): void
    {
        $admin = User::where('email', 'admin@abtc.gov.ph')->first();
        $this->assertNotNull($admin);

        $routes = [
            'dashboard',
            'patients.index', 'patients.create',
            'queue.index', 'queue.data',
            'schedules.index', 'schedules.overdue',
            'inventory.index', 'inventory.create',
            'sms.index', 'sms.template',
            'reports.index', 'reports.export.csv', 'reports.export.pdf',
            'settings.index', 'settings.users', 'settings.audit',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($admin)->get(route($route));
            if ($response->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
                $this->assertTrue(true, "Streamed response for {$route}");
                continue;
            }
            $status = $response->status();
            if ($status !== 200) {
                fwrite(STDERR, "ROUTE FAIL: {$route} -> {$status}\n");
                if ($response->exception) {
                    fwrite(STDERR, "  exc: ".get_class($response->exception).': '.$response->exception->getMessage()."\n");
                }
            }
            $this->assertFalse(
                $response->isRedirect() && str_contains($response->headers->get('Location'), '/login'),
                "Route {$route} redirected to login"
            );
            $response->assertStatus(200, "Route {$route} failed with ".$response->status());
        }
    }

    public function test_patient_registration_creates_ticket_and_doctor_plan_creates_schedules(): void
    {
        $staff = User::where('email', 'nurse@abtc.gov.ph')->first();
        $before = \App\Models\Ticket::count();

        $response = $this->actingAs($staff)->post(route('patients.store'), [
            'first_name' => 'Test', 'last_name' => 'Patient', 'sex' => 'Male',
            'dob' => '1990-01-01', 'contact_number' => '09171234567',
            'address' => '123 Test St', 'barangay' => 'Barangka',
            'date_of_bite' => now()->toDateString(), 'animal_type' => 'Dog',
            'register_queue' => true, 'patient_type' => 'regular',
        ]);

        $response->assertRedirect();
        $this->assertEquals($before + 1, \App\Models\Ticket::count());
        $patient = \App\Models\Patient::latest('id')->firstOrFail();
        $this->assertSame(0, $patient->vaccinationSchedules()->count());
        $doctor = User::where('role', 'Doctor')->firstOrFail();
        $this->actingAs($doctor)->put(route('patients.plan.store', $patient), [
            'exposure_category' => 'III', 'schedule' => 'days_0_3_7_28',
            'vaccine_type' => 'PVRV', 'planned_dose_count' => 4,
        ])->assertRedirect(route('patients.show', $patient));
        $this->assertEquals(4, $patient->vaccinationSchedules()->count());
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        foreach (['Doctor', 'Nurse'] as $role) {
            $user = User::where('role', $role)->first();
            $this->assertNotNull($user);
            $response = $this->actingAs($user)->get(route('settings.index'));
            $response->assertForbidden(); // 403 from role middleware
        }
    }

    public function test_admin_can_edit_and_delete_user_accounts_safely(): void
    {
        $admin = User::where('email', 'admin@abtc.gov.ph')->firstOrFail();
        $target = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com', 'role' => 'Staff']);

        $this->actingAs($admin)->get(route('settings.users.edit', $target))
            ->assertOk()
            ->assertSee('Old Name');

        $this->put(route('settings.users.update', $target), [
            'name' => 'Updated Name', 'email' => 'updated@example.com',
            'role' => 'Nurse', 'contact_number' => '09171234567',
        ])->assertRedirect(route('settings.users'));

        $this->assertDatabaseHas('users', [
            'id' => $target->id, 'name' => 'Updated Name',
            'email' => 'updated@example.com', 'role' => 'Nurse',
        ]);

        $this->delete(route('settings.users.destroy', $target))
            ->assertRedirect(route('settings.users'));
        $this->assertDatabaseMissing('users', ['id' => $target->id]);

        $this->delete(route('settings.users.destroy', $admin))
            ->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'Admin']);
    }

    public function test_admin_cannot_remove_the_last_admin_role(): void
    {
        $admin = User::where('email', 'admin@abtc.gov.ph')->firstOrFail();
        $this->actingAs($admin)->put(route('settings.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email,
            'role' => 'Nurse', 'contact_number' => $admin->contact_number,
        ])->assertSessionHasErrors('role');

        $this->assertSame('Admin', $admin->fresh()->role);
    }

public function test_dose_administer_deducts_inventory_atomically(): void
    {
        $doctor = User::where('role', 'Doctor')->first();
        $batch = $this->batch;
        $qtyBefore = $batch->quantity;

        $patient = Patient::create([
            'first_name' => 'Dose', 'last_name' => 'Test', 'dob' => '1990-01-01', 'sex' => 'Female',
            'address' => 'Test address', 'barangay' => 'Barangka', 'patient_type' => 'regular',
            'vaccine_type' => 'PVRV', 'planned_dose_count' => 4,
        ]);
        $schedule = VaccinationSchedule::create([
            'patient_id' => $patient->id, 'dose_number' => 1,
            'scheduled_date' => now()->toDateString(), 'status' => 'scheduled',
        ]);
        $response = $this->actingAs($doctor)->post(route('schedules.administer', $schedule), [
            'vaccine_type' => 'PVRV', 'inventory_id' => $batch->id,
        ]);

        $response->assertRedirect();
        $this->assertEquals($qtyBefore - 1, $batch->fresh()->quantity);
        $this->assertEquals('administered', $schedule->fresh()->status);
    }
}
