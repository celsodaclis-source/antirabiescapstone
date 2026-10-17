<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$pdo = DB::connection()->getPdo();
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$fileTables = array_filter($tables, fn($t) => str_contains($t, 'file'));
echo "File-related tables: " . implode(', ', $fileTables) . "\n";
echo "FileMaintenance model table: " . (new App\Models\FileMaintenance)->getTable() . "\n";
