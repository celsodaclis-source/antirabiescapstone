<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf', [
        'from' => '2026-08-01',
        'to' => '2026-08-27',
        'stats' => [
            'patients_registered' => 10,
            'patients_served' => 8,
            'vaccinations_administered' => 15,
            'missed_appointments' => 2,
            'overdue_followups' => 1,
            'inventory_used' => 50,
            'new_inventory_received' => 100,
            'sms_sent' => 25
        ],
        'topBarangays' => collect(['Barangay A' => 5, 'Barangay B' => 3]),
        'exposureBreakdown' => collect(['Category I' => 10, 'Category II' => 5])
    ]);
    $output = $pdf->output();
    echo 'PDF generated successfully, size: ' . strlen($output) . ' bytes';
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
