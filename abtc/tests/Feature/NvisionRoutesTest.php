<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NvisionRoutesTest extends TestCase
{
    use RefreshDatabase;

    /** Seed the isolated test database with the demo records used below. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
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

    public function test_patient_registration_flow_creates_ticket_and_schedules(): void
    {
        $staff = User::where('email', 'nurse@abtc.gov.ph')->first();
        $before = \App\Models\Ticket::count();

        $response = $this->actingAs($staff)->post(route('patients.store'), [
            'first_name' => 'Test', 'last_name' => 'Patient', 'sex' => 'Male',
            'dob' => '1990-01-01', 'contact_number' => '09171234567',
            'address' => '123 Test St', 'barangay' => 'Barangka',
            'date_of_bite' => now()->toDateString(), 'animal_type' => 'Dog',
            'exposure_category' => 'III', 'register_queue' => true,
            'patient_type' => 'regular',
        ]);

        $response->assertRedirect();
        $this->assertEquals($before + 1, \App\Models\Ticket::count());
        $this->assertEquals(4, \App\Models\VaccinationSchedule::where('patient_id', \App\Models\Patient::max('id'))->count());
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

public function test_dose_administer_deducts_inventory_atomically(): void
    {
        $doctor = User::where('role', 'Doctor')->first();
        $batch = \App\Models\VaccineInventory::where('quantity', '>', 0)->first();
        $qtyBefore = $batch->quantity;

        $schedule = \App\Models\VaccinationSchedule::where('status', 'scheduled')->first();
        $response = $this->actingAs($doctor)->post(route('schedules.administer', $schedule), [
            'inventory_id' => $batch->id,
        ]);

        $response->assertRedirect();
        $this->assertEquals($qtyBefore - 1, $batch->fresh()->quantity);
        $this->assertEquals('administered', $schedule->fresh()->status);
    }
}
