<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FileMaintenance;
use App\Models\User;

$admin = User::where('email', 'admin@abtc.gov.ph')->first();

FileMaintenance::create(['file_number' => 'FM-2024-001', 'title' => 'Anti-Rabies Vaccination Protocol', 'description' => 'Standard operating procedure for anti-rabies vaccination administration.', 'category' => 'Protocol', 'status' => 'active', 'effective_date' => now()->subMonths(6), 'expiry_date' => now()->addMonths(6), 'tags' => ['vaccination', 'protocol', 'rabies'], 'created_by' => $admin->id, 'updated_by' => $admin->id]);
FileMaintenance::create(['file_number' => 'FM-2024-002', 'title' => 'Patient Data Privacy Policy', 'description' => 'Policy on handling patient personal health information in compliance with RA 10173.', 'category' => 'Policy', 'status' => 'active', 'effective_date' => now()->subYear(), 'expiry_date' => now()->addYear(), 'tags' => ['privacy', 'RA10173', 'compliance'], 'created_by' => $admin->id, 'updated_by' => $admin->id]);
FileMaintenance::create(['file_number' => 'FM-2024-003', 'title' => 'Vaccine Cold Chain Guidelines', 'description' => 'Guidelines for proper storage and handling of vaccines to maintain cold chain integrity.', 'category' => 'Guideline', 'status' => 'active', 'effective_date' => now()->subMonths(3), 'expiry_date' => now()->addMonths(9), 'tags' => ['vaccine', 'storage', 'cold-chain'], 'created_by' => $admin->id, 'updated_by' => $admin->id]);
FileMaintenance::create(['file_number' => 'FM-2023-015', 'title' => 'Previous Year Inventory Report', 'description' => 'Annual inventory report for the previous fiscal year.', 'category' => 'Report', 'status' => 'archived', 'effective_date' => now()->subYears(1)->startOfYear(), 'expiry_date' => now()->subYears(1)->endOfYear(), 'tags' => ['inventory', 'annual', 'report'], 'created_by' => $admin->id, 'updated_by' => $admin->id]);
FileMaintenance::create(['file_number' => 'FM-2024-004', 'title' => 'SMS Reminder Template', 'description' => 'Template for SMS reminders sent to patients for vaccination schedules.', 'category' => 'Template', 'status' => 'draft', 'effective_date' => null, 'expiry_date' => null, 'tags' => ['sms', 'template', 'reminder'], 'created_by' => $admin->id, 'updated_by' => $admin->id]);

echo "Seeded " . FileMaintenance::count() . " records.\n";
