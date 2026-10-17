<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pdo = DB::connection()->getPdo();

// Drop the old singular table if it exists
$pdo->exec("DROP TABLE IF EXISTS file_maintenance");

// Remove the migration record so we can re-run it
DB::table('migrations')->where('migration', '2026_07_25_000001_create_file_maintenance_table')->delete();

echo "Cleaned up old migration record and table.\n";
