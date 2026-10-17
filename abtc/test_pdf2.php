<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simulate the exportPdf method logic
$from = '2026-08-01';
$to = '2026-08-27';
$fromDt = \Carbon\Carbon::parse($from)->startOfDay();
$toDt = \Carbon\Carbon::parse($to)->endOfDay();

try {
    $topBarangays = \App\Models\Patient::whereBetween('created_at', [$fromDt, $toDt])
        ->get()
        ->groupBy('barangay')
        ->map(fn ($g) => $g->count())
        ->sortDesc()
        ->take(10);

    $exposureBreakdown = \App\Models\BiteIncident::whereBetween('created_at', [$fromDt, $toDt])
        ->get()
        ->groupBy('exposure_category')
        ->map(fn ($g) => $g->count());

    $stats = [
        'patients_registered' => 0,
        'patients_served' => 0,
        'vaccinations_administered' => 0,
        'missed_appointments' => 0,
        'overdue_followups' => 0,
        'inventory_used' => 0,
        'new_inventory_received' => 0,
        'sms_sent' => 0,
    ];

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf', compact('from', 'to', 'stats', 'topBarangays', 'exposureBreakdown'));
    $output = $pdf->output();

    // Save to file to verify
    file_put_contents('test_output.pdf', $output);
    echo 'PDF generated and saved to test_output.pdf, size: ' . strlen($output) . ' bytes';
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'File: ' . $e->getFile() . ':' . $e->getLine() . "\n";
    echo $e->getTraceAsString();
}
