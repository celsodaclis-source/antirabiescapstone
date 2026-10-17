<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pdo = DB::connection()->getPdo();
$tables = $pdo->query("SHOW TABLES LIKE 'file_maintenance'")->fetchAll(PDO::FETCH_COLUMN);
echo "file_maintenance tables: " . count($tables) . "\n";

$migrations = DB::table('migrations')->where('migration', 'like', '%file_maintenance%')->get();
echo "Migration records: " . count($migrations) . "\n";
foreach ($migrations as $m) {
    echo $m->migration . " batch=" . $m->batch . "\n";
}
