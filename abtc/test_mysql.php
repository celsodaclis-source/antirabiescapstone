<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=abtc_db', 'root', '');
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "MySQL connected. Tables: " . count($tables) . "\n";
    foreach ($tables as $t) {
        if (strpos($t, 'file') !== false || strpos($t, 'maintenance') !== false) {
            echo "  FOUND: " . $t . "\n";
        }
    }
} catch (Exception $e) {
    echo "MySQL connection failed: " . $e->getMessage() . "\n";
}
