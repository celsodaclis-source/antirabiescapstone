<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "=== tickets columns ===\n";
$tcols = Schema::getColumnListing('tickets');
echo implode(', ', $tcols) . "\n";
echo "has is_priority: " . ( Schema::hasColumn('tickets','is_priority') ? 'yes' : 'no' ) . "\n";
echo "has counter_assigned: " . ( Schema::hasColumn('tickets','counter_assigned') ? 'yes' : 'no' ) . "\n";
echo "has assigned_staff: " . ( Schema::hasColumn('tickets','assigned_staff') ? 'yes' : 'no' ) . "\n";

echo "\n=== vaccination_schedules columns ===\n";
$scols = Schema::getColumnListing('vaccination_schedules');
echo implode(', ', $scols) . "\n";

echo "\n=== vaccine_inventory columns ===\n";
$icols = Schema::getColumnListing('vaccine_inventory');
echo implode(', ', $icols) . "\n";
