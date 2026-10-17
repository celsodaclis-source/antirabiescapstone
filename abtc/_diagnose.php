<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccineInventory;
use App\Models\VaccinationSchedule;
use App\Models\BiteIncident;

echo "=== Row counts ===\n";
echo "Users: " . User::count() . "\n";
echo "Patients: " . Patient::count() . "\n";
echo "Tickets: " . Ticket::count() . "\n";
echo "VaccineInventory: " . VaccineInventory::count() . "\n";
echo "VaccinationSchedule: " . VaccinationSchedule::count() . "\n";
echo "BiteIncident: " . BiteIncident::count() . "\n";

echo "\n=== Schema checks ===\n";

// Check patients columns
$cols = Schema::getColumnListing('patients');
echo "patients columns: " . implode(', ', $cols) . "\n";
echo "has patient_type: " . ( Schema::hasColumn('patients','patient_type') ? 'yes' : 'no' ) . "\n";
echo "has priority columns (is_pwd, is_senior, is_pregnant, is_emergency): " . 
  (Schema::hasColumn('patients','is_pwd')?'is_pwd=yes':'is_pwd=no') . " " .
  (Schema::hasColumn('patients','is_senior')?'is_senior=yes':'is_senior=no') . " " .
  (Schema::hasColumn('patients','is_pregnant')?'is_pregnant=yes':'is_pregnant=no') . " " .
  (Schema::hasColumn('patients','is_emergency')?'is_emergency=yes':'is_emergency=no') . "\n";
;

// Check tickets columns
$tcols = Schema::getColumnListing('tickets');
echo "\ntickets columns: " . implode(', ', $cols) . "\n";
echo "has is_priority: " . ( Schema::hasColumn('tickets','is_priority') ? 'yes' : 'no' ) . "\n";
echo "has counter_assigned: " . ( Schema::hasColumn('tickets','counter_assigned') ? 'yes' : 'no' ) . "\n";
echo "has assigned_staff: " . ( Schema::hasColumn('tickets','assigned_staff') ? 'yes' : 'no' ) . "\n";
